<?php
declare(strict_types = 1);

namespace de\phosco\mattermost\whatsapp;

class Post {

    private $day;

    private $time;

    private $user;

    private $content;

    public function __construct(string $day, string $time, string $user, string $content) {

        $this->day = $day;
        $this->time = $time;
        $this->user = $user;
        $this->content = $this->processContent($content);
    }

    private function processContent(string $content): array {

        $media = self::extractMedia($content);
        if ($media === null) {
            return $this->splitOnEmoji($content);
        }

        $res = array();
        if (trim($media['before']) !== '') {
            $res = array_merge($res, $this->splitOnEmoji(rtrim($media['before'])));
        }
        $res[] = new Media($media['file'], $media['original']);
        if (trim($media['after']) !== '') {
            $res = array_merge($res, array(new Text(" ")), $this->splitOnEmoji(ltrim($media['after'])));
        }
        return $res;
    }

    private function splitOnEmoji(string $content): array {

        $res = array();

        $c = preg_replace('/\xf0\x9f\x8f\xbb|\xf0\x9f\x8f\xbc/', '', $content); // sex and skin color
        $c = preg_replace('/\xe2.{2}\xe2.{2}\xef.{2}/', '', $c);

        $parts = preg_split('/(\xf0\x9f.{2}|\xe2.{2}\xef.{2})/', $c, 2, PREG_SPLIT_DELIM_CAPTURE);
        if (count($parts) > 1) {
            $res[] = new Text($parts[0]);
            $res[] = new Emoji($parts[1]);
            $res = array_merge($res, $this->splitOnEmoji($parts[2]));
        } else {
            $parts = preg_split('/@(\w+)/', $c, 2, PREG_SPLIT_DELIM_CAPTURE);
            if (count($parts) > 1) {
                $res[] = new Text($parts[0]);
                $res[] = new Mention($parts[1]);
                $res = array_merge($res, $this->splitOnEmoji($parts[2]));
            } else {
                $res[] = new Text($c);
            }
        }

        return $res;
    }

    /**
     * Captions WhatsApp appends to attached files when no LRM marker is present
     * (Android "Export chat" -> "Include media", lower case, per UI language).
     */
    private const ATTACHED_PHRASES = array(
        'file attached', 'dosya ekli', 'datei angehängt', 'archivo adjunto', 'arquivo anexado',
        'ficheiro anexado', 'fichier joint', 'file allegato', 'bestand bijgevoegd', 'plik załączony',
        'файл прикреплен', 'файл прикреплён', 'fișier atașat', 'fil bifogad', 'fil vedhæftet', 'fil vedlagt'
    );

    /**
     * Detect a media reference in a chat line.
     *
     * Supported formats:
     *  - Android: "\u{200E}IMG-20230723-WA0001.jpg (file attached)" (caption localised, e.g. "(dosya ekli)")
     *  - Android without the LRM marker (stripped by editors/other tools)
     *  - iOS:     "\u{200E}<attached: 00000012-PHOTO-2023-07-23-14-23-00.jpg>" (label localised, e.g. "<ekli: ...>")
     *
     * @return array{before:string,file:string,after:string,original:string}|null
     */
    public static function extractMedia(string $line): ?array {

        $ext = '\\.[\\p{L}\\p{N}]{1,10}';

        // iOS: <attached: file.ext>
        if (preg_match('/^(.*?)\\x{200E}?(<\\s*[^<>:]{1,40}:\\s*([^<>]+?' . $ext . ')\\s*>)(.*)$/u', $line, $m) === 1) {
            return array('before' => $m[1], 'file' => $m[3], 'after' => $m[4], 'original' => $m[2]);
        }

        // Android with LRM marker: \u{200E}file.ext (file attached)
        if (preg_match('/^(.*?)\\x{200E}((.+?' . $ext . ') \\([^()]{1,60}\\))(.*)$/u', $line, $m) === 1) {
            return array('before' => $m[1], 'file' => $m[3], 'after' => $m[4], 'original' => $m[2]);
        }

        // Android without LRM marker: the whole line must be "file.ext (caption)"
        if (preg_match('/^\\s*((\\S.*?' . $ext . ') \\(([^()]{1,60})\\))\\s*$/u', $line, $m) === 1) {
            $phrase = function_exists('mb_strtolower') ? mb_strtolower(trim($m[3]), 'UTF-8') : strtolower(trim($m[3]));
            if (preg_match('/\\s/u', $m[2]) !== 1 || in_array($phrase, self::ATTACHED_PHRASES, true)) {
                return array('before' => '', 'file' => $m[2], 'after' => '', 'original' => $m[1]);
            }
        }

        return null;
    }

    public function append(string $line) {

        $c = $this->processContent($line);
        $this->content = array_merge($this->content, array(new Text("\n")), $c);
    }

    public function getDay() {

        return $this->day;
    }

    public function getTime() {

        return $this->time;
    }

    public function getUser() {

        return $this->user;
    }

    public function getContent() {

        return $this->content;
    }
}
?>
