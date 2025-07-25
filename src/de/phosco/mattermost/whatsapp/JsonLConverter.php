<?php
declare(strict_types = 1);

namespace de\phosco\mattermost\whatsapp;

class JsonLConverter {

    private $team;

    private $channel;
    
    // Mattermost message length limit - can be configured via environment
    private $maxMessageLength;
    private const DEFAULT_MAX_MESSAGE_LENGTH = 16000; // Leave some buffer for safety
    private const CONTINUATION_SUFFIX = "\n\n... (continued)";
    private const CONTINUATION_PREFIX = "(continued) ...\n\n";

    public function __construct(string $team, string $channel) {

        $this->team = $team;
        $this->channel = $channel;
        
        // Get max message length from environment or use default
        $this->maxMessageLength = (int) ($_ENV['MAX_MESSAGE_LENGTH'] ?? $_SERVER['MAX_MESSAGE_LENGTH'] ?? self::DEFAULT_MAX_MESSAGE_LENGTH);
        
        // Ensure the limit is reasonable
        if ($this->maxMessageLength <= 0 || $this->maxMessageLength > 16383) {
            $this->maxMessageLength = self::DEFAULT_MAX_MESSAGE_LENGTH;
        }
    }

    /**
     * Split a long message into multiple parts that fit within Mattermost's length limit
     */
    private function splitLongMessage(string $message): array {
        if (mb_strlen($message, 'UTF-8') <= $this->maxMessageLength) {
            return [$message];
        }
        
        $parts = [];
        $currentMessage = '';
        $lines = explode("\n", $message);
        $lineIndex = 0;
        
        while ($lineIndex < count($lines)) {
            $line = $lines[$lineIndex];
            $testMessage = $currentMessage . ($currentMessage ? "\n" : "") . $line;
            
            // Check if adding this line would exceed the limit
            if (mb_strlen($testMessage, 'UTF-8') > $this->maxMessageLength) {
                if (!empty($currentMessage)) {
                    // Add current message part with continuation suffix
                    $parts[] = $currentMessage . self::CONTINUATION_SUFFIX;
                    $currentMessage = '';
                }
                
                // Handle case where a single line is too long
                if (mb_strlen($line, 'UTF-8') > $this->maxMessageLength) {
                    $chunks = $this->splitLongLine($line);
                    foreach ($chunks as $i => $chunk) {
                        if ($i === 0 && count($parts) > 0) {
                            $parts[] = self::CONTINUATION_PREFIX . $chunk . self::CONTINUATION_SUFFIX;
                        } elseif ($i === count($chunks) - 1) {
                            $currentMessage = self::CONTINUATION_PREFIX . $chunk;
                        } else {
                            $parts[] = self::CONTINUATION_PREFIX . $chunk . self::CONTINUATION_SUFFIX;
                        }
                    }
                } else {
                    $currentMessage = (count($parts) > 0 ? self::CONTINUATION_PREFIX : '') . $line;
                }
            } else {
                $currentMessage = $testMessage;
            }
            
            $lineIndex++;
        }
        
        // Add any remaining content
        if (!empty($currentMessage)) {
            $parts[] = $currentMessage;
        }
        
        return $parts;
    }
    
    /**
     * Split a single line that's too long into multiple chunks
     */
    private function splitLongLine(string $line): array {
        $parts = [];
        $maxChunkLength = $this->maxMessageLength - mb_strlen(self::CONTINUATION_PREFIX . self::CONTINUATION_SUFFIX, 'UTF-8');
        
        // Try to split on word boundaries first
        $words = explode(' ', $line);
        $currentChunk = '';
        
        foreach ($words as $word) {
            $testChunk = $currentChunk . ($currentChunk ? ' ' : '') . $word;
            
            if (mb_strlen($testChunk, 'UTF-8') > $maxChunkLength) {
                if (!empty($currentChunk)) {
                    $parts[] = $currentChunk;
                    $currentChunk = $word;
                } else {
                    // Single word is too long, split it character by character
                    $parts = array_merge($parts, $this->splitLongWord($word, $maxChunkLength));
                    $currentChunk = '';
                }
            } else {
                $currentChunk = $testChunk;
            }
        }
        
        if (!empty($currentChunk)) {
            $parts[] = $currentChunk;
        }
        
        return $parts;
    }
    
    /**
     * Split a single word that's too long
     */
    private function splitLongWord(string $word, int $maxLength): array {
        $parts = [];
        $length = mb_strlen($word, 'UTF-8');
        
        for ($i = 0; $i < $length; $i += $maxLength) {
            $parts[] = mb_substr($word, $i, $maxLength, 'UTF-8');
        }
        
        return $parts;
    }

    public function toJsonL(WhatsAppUserMap $userMap, WhatsAppPhoneMap $phoneMap, WhatsAppEmojiMap $emojiMap,
            WhatsAppChat $chat): string {

        $json = array(array("type" => "version", "version" => 1));
        $template = array("type" => "post", "post" => array("team" => $this->team, "channel" => $this->channel));

        foreach ($chat->getPosts() as $post) {

            $msg = "";
            foreach ($post->getContent() as $content) {
                if ($content instanceof Text) {
                    $msg .= $content->getContent();
                }
                if ($content instanceof Emoji) {
                    $msg .= ($this->endsWith($msg, " ") ? "" : " ") . $emojiMap->get($content->getBinary()) . " ";
                }
                if ($content instanceof PhoneNumber) {
                    $msg .= ($this->endsWith($msg, " ") ? "" : " ") . '@' . $phoneMap->get($content->getContent()) .
                            " ";
                }
            }

            // Collect media attachments
            $media = array();
            foreach ($post->getContent() as $content) {
                if ($content instanceof Media) {
                    $media[] = array("path" => "data/" . $content->getContent());
                }
            }

            // Split message if it's too long
            $messageParts = $this->splitLongMessage($msg);
            
            foreach ($messageParts as $index => $messagePart) {
                $jsonPost = $template;
                $jsonPost["post"]["user"] = $userMap->get($post->getUser());
                $jsonPost["post"]["message"] = $messagePart;
                $jsonPost["post"]["create_at"] = $this->toUnixTime($post->getDay(), $post->getTime());
                
                // Only add media attachments to the first part of a split message
                if ($index === 0 && count($media) > 0) {
                    $jsonPost["post"]["attachments"] = $media;
                }
                
                $json[] = $jsonPost;
            }
        }

        $res = "";
        foreach ($json as $obj) {
            $res .= "\n" . json_encode($obj, JSON_UNESCAPED_UNICODE);
        }

        return substr($res, 1);
    }

    public function toArray(WhatsAppUserMap $userMap, WhatsAppPhoneMap $phoneMap, WhatsAppEmojiMap $emojiMap,
            WhatsAppChat $chat): array {

        $json = array(array("type" => "version", "version" => 1));
        $template = array("type" => "post", "post" => array("team" => $this->team, "channel" => $this->channel));

        foreach ($chat->getPosts() as $post) {

            $msg = "";
            foreach ($post->getContent() as $content) {
                if ($content instanceof Text) {
                    $msg .= $content->getContent();
                }
                if ($content instanceof Emoji) {
                    $msg .= ($this->endsWith($msg, " ") ? "" : " ") . $emojiMap->get($content->getBinary()) . " ";
                }
                if ($content instanceof PhoneNumber) {
                    $msg .= ($this->endsWith($msg, " ") ? "" : " ") . '@' . $phoneMap->get($content->getContent()) .
                            " ";
                }
            }

            // Collect media attachments
            $media = array();
            foreach ($post->getContent() as $content) {
                if ($content instanceof Media) {
                    $media[] = array("path" => "data/" . $content->getContent());
                }
            }

            // Split message if it's too long
            $messageParts = $this->splitLongMessage($msg);
            
            foreach ($messageParts as $index => $messagePart) {
                $jsonPost = $template;
                $jsonPost["post"]["user"] = $userMap->get($post->getUser());
                $jsonPost["post"]["message"] = $messagePart;
                $jsonPost["post"]["create_at"] = $this->toUnixTime($post->getDay(), $post->getTime());
                
                // Only add media attachments to the first part of a split message
                if ($index === 0 && count($media) > 0) {
                    $jsonPost["post"]["attachments"] = $media;
                }
                
                $json[] = $jsonPost;
            }
        }

        return $json;
    }

    private function endsWith(string $haystack, string $needle): bool {

        $length = strlen($needle);
        if (!$length) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }

    private function toUnixTime(string $day, string $time): int {

        // dd.mm.yyyy hh:mi -> Y-m-d H:i:s
        $val = substr($day, 6, 4) . "-" . substr($day, 3, 2) . "-" . substr($day, 0, 2) . " " . $time . ":00";
        // error_log("$day $time -> $val");
        return strtotime($val) * 1000;
    }

}
?>
