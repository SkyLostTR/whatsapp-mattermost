<?php
declare(strict_types = 1);

namespace de\phosco\mattermost\whatsapp;

class WhatsAppChat {

    /**
     * Start of a message, e.g.
     *  Android: "23.07.2023 14:23 - Name: text", "7/23/23, 2:23 PM - Name: text"
     *  iOS:     "[23.07.2023 14:23:45] Name: text", "[7/23/23, 2:23:45 PM] Name: text"
     */
    private const HEADER = '/^[\x{FEFF}\x{200E}\x{200F}]*(\[)?(\d{1,4})[.\/-](\d{1,2})[.\/-](\d{1,4}),?\s+(\d{1,2})[:.](\d{2})(?:[:.](\d{2}))?(?:[\s\x{202F}\x{00A0}]*([AaPp])\.?\s?[Mm]\.?)?(?(1)\]\s*|\s+[-\x{2013}]\s+)(.*)$/u';

    private const INVISIBLE_CHARS = '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{FEFF}]/u';

    private $chat;

    private $chatFile;

    private $chatDir;

    private $mediaDir;

    /**
     * @param string      $chatFile The exported chat text file
     * @param string|null $mediaDir Folder with the exported media files (defaults to the folder of the chat file)
     * @param string|null $dateOrder "DMY", "MDY" or "YMD"; null detects the order from the export
     */
    public function __construct(string $chatFile, ?string $mediaDir = null, ?string $dateOrder = null) {

        $this->chatFile = $chatFile;
        $this->chatDir = dirname($chatFile);
        $this->mediaDir = ($mediaDir !== null && $mediaDir !== '') ? $mediaDir : $this->chatDir;
        $this->chat = $this->import($chatFile, $dateOrder !== null ? strtoupper($dateOrder) : null);
    }

    private function import(string $chatFile, ?string $dateOrder): array {

        $handle = fopen($chatFile, "r");
        if ($handle === false) {
            throw new \RuntimeException("Cannot open chat file " . $chatFile);
        }

        // First pass: split into messages, keeping the raw date parts
        $messages = array();
        $current = null;
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            $m = array();
            if (preg_match(self::HEADER, $line, $m) === 1) {
                if ($current !== null) {
                    $messages[] = $current;
                }
                $current = array(
                    'date' => array((int) $m[2], (int) $m[3], (int) $m[4], strlen($m[2])),
                    'hour' => (int) $m[5],
                    'minute' => (int) $m[6],
                    'second' => $m[7] !== '' ? (int) $m[7] : null,
                    'ampm' => isset($m[8]) ? strtolower($m[8]) : '',
                    'rest' => $m[9],
                    'lines' => array()
                );
                continue;
            }

            if (trim($line) === '') {
                continue;
            }
            if ($current !== null) {
                $current['lines'][] = trim($line);
            }
        }
        fclose($handle);

        if ($current !== null) {
            $messages[] = $current;
        }

        if ($dateOrder === null) {
            $dateOrder = self::detectDateOrder(array_column($messages, 'date'));
        }

        // Second pass: build the posts
        $res = array();
        foreach ($messages as $message) {
            $day = self::formatDay($message['date'], $dateOrder);
            $time = self::formatTime($message['hour'], $message['minute'], $message['second'], $message['ampm']);

            $user = "System";
            $content = $message['rest'];
            $parts = array();
            if (preg_match('/^([^:]+?): (.*)$/su', $message['rest'], $parts) === 1) {
                $user = self::cleanName($parts[1]);
                $content = $parts[2];
            } elseif (preg_match('/^([^:]+?):$/su', $message['rest'], $parts) === 1) {
                $user = self::cleanName($parts[1]);
                $content = '';
            }

            $post = new Post($day, $time, $user, $content);
            foreach ($message['lines'] as $line) {
                $post->append($line);
            }
            $res[] = $post;
        }

        return $res;
    }

    /**
     * WhatsApp wraps phone numbers and some names in invisible bidi markers.
     */
    public static function cleanName(string $name): string {

        $clean = preg_replace(self::INVISIBLE_CHARS, '', $name);
        return trim($clean === null ? $name : $clean);
    }

    /**
     * @param array<int,array{0:int,1:int,2:int,3:int}> $dates
     */
    private static function detectDateOrder(array $dates): string {

        $firstMayBeMonth = true;
        $secondMayBeMonth = true;
        foreach ($dates as $date) {
            if ($date[3] === 4) {
                return 'YMD';
            }
            if ($date[0] > 12) {
                $firstMayBeMonth = false;
            }
            if ($date[1] > 12) {
                $secondMayBeMonth = false;
            }
        }
        if (!$secondMayBeMonth && $firstMayBeMonth) {
            return 'MDY';
        }
        return 'DMY';
    }

    private static function formatDay(array $date, string $order): string {

        switch ($order) {
            case 'MDY':
                list($month, $day, $year) = $date;
                break;
            case 'YMD':
                list($year, $month, $day) = $date;
                break;
            default:
                list($day, $month, $year) = $date;
        }
        if ($year < 100) {
            $year += 2000;
        }
        return sprintf('%02d.%02d.%04d', $day, $month, $year);
    }

    private static function formatTime(int $hour, int $minute, ?int $second, string $ampm): string {

        if ($ampm === 'p' && $hour < 12) {
            $hour += 12;
        } elseif ($ampm === 'a' && $hour === 12) {
            $hour = 0;
        }
        $time = sprintf('%02d:%02d', $hour, $minute);
        return $second === null ? $time : $time . sprintf(':%02d', $second);
    }

    public function getPosts(): array {

        return $this->chat;
    }

    public function getChatFile(): string {

        return $this->chatFile;
    }

    public function getMediaFolder(): string {

        return $this->mediaDir;
    }
}
?>
