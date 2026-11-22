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

    /** @var MediaResolver|null */
    private $resolver;

    /** @var \DateTimeZone */
    private $timezone;

    /** @var array<string,string> */
    private $attachments = array();

    /** @var array<int,array{file:string,day:string,time:string,user:string}> */
    private $missingMedia = array();

    /** @var int */
    private $mediaReferences = 0;

    /**
     * @param string             $team     Mattermost team name
     * @param string             $channel  Mattermost channel name
     * @param MediaResolver|null $resolver Media lookup; defaults to the chat's media folder
     * @param string|null        $timezone Time zone of the export's timestamps (default: PHP default time zone)
     */
    public function __construct(string $team, string $channel, ?MediaResolver $resolver = null, ?string $timezone = null) {

        $this->team = $team;
        $this->channel = $channel;
        $this->resolver = $resolver;
        $this->timezone = new \DateTimeZone($timezone !== null && $timezone !== '' ? $timezone : date_default_timezone_get());
        
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

    /**
     * Converts the chat into Mattermost bulk import JSONL.
     *
     * Attachment paths are relative to the "data/" folder of the import archive
     * (Mattermost prefixes them with "data/" itself), see {@see getAttachments()}
     * for the files that have to be packaged.
     */
    public function toJsonL(WhatsAppUserMap $userMap, WhatsAppPhoneMap $phoneMap, WhatsAppEmojiMap $emojiMap,
            WhatsAppChat $chat): string {

        $lines = array();
        foreach ($this->toArray($userMap, $phoneMap, $emojiMap, $chat) as $obj) {
            $lines[] = json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return implode("\n", $lines);
    }

    public function toArray(WhatsAppUserMap $userMap, WhatsAppPhoneMap $phoneMap, WhatsAppEmojiMap $emojiMap,
            WhatsAppChat $chat): array {

        $resolver = $this->resolver;
        if ($resolver === null || $resolver->getBaseDir() !== rtrim($chat->getMediaFolder(), "/\\")) {
            $resolver = new MediaResolver($chat->getMediaFolder(), array($chat->getChatFile()));
        }

        $this->attachments = array();
        $this->missingMedia = array();
        $this->mediaReferences = 0;
        $usedTimestamps = array();

        $json = array(array("type" => "version", "version" => 1));
        $template = array("type" => "post", "post" => array("team" => $this->team, "channel" => $this->channel));

        foreach ($chat->getPosts() as $post) {

            $msg = "";
            $media = array();
            foreach ($post->getContent() as $content) {
                if ($content instanceof Text) {
                    $msg .= $content->getContent();
                } elseif ($content instanceof Emoji) {
                    $msg .= ($this->endsWith($msg, " ") || $msg === "" ? "" : " ") . $emojiMap->get($content->getBinary()) . " ";
                } elseif ($content instanceof PhoneNumber) {
                    $msg .= ($this->endsWith($msg, " ") ? "" : " ") . '@' . $phoneMap->get($content->getContent()) .
                            " ";
                } elseif ($content instanceof Mention) {
                    $name = $content->getUsername();
                    $msg .= '@' . (ctype_digit($name) && $phoneMap->has($name) ? $phoneMap->get($name) : $name);
                } elseif ($content instanceof Media) {
                    $this->mediaReferences++;
                    $relative = $resolver->resolve($content->getContent());
                    if ($relative === null) {
                        // Keep the reference visible instead of silently dropping it
                        $this->missingMedia[] = array(
                            "file" => $content->getContent(),
                            "day" => $post->getDay(),
                            "time" => $post->getTime(),
                            "user" => $post->getUser()
                        );
                        $msg .= $content->getOriginal();
                        continue;
                    }
                    $this->attachments[$relative] = $resolver->getAbsolutePath($relative);
                    $media[] = array("path" => $relative);
                }
            }
            $msg = trim($msg);

            // Split message if it's too long
            $messageParts = $this->splitLongMessage($msg);
            $baseTime = $this->toUnixTime($post->getDay(), $post->getTime());

            foreach ($messageParts as $index => $messagePart) {
                // WhatsApp only has minute (Android) or second (iOS) precision. Mattermost
                // treats posts in the same channel with the same create_at and message as the
                // same post and overwrites them - e.g. several photos sent within one minute.
                // Give every post a unique, order preserving timestamp.
                $createAt = $baseTime;
                while (isset($usedTimestamps[$createAt])) {
                    $createAt++;
                }
                $usedTimestamps[$createAt] = true;

                $jsonPost = $template;
                $jsonPost["post"]["user"] = $userMap->get($post->getUser());
                $jsonPost["post"]["message"] = $messagePart;
                $jsonPost["post"]["create_at"] = $createAt;

                // Only add media attachments to the first part of a split message
                if ($index === 0 && count($media) > 0) {
                    $jsonPost["post"]["attachments"] = $media;
                }

                $json[] = $jsonPost;
            }
        }

        return $json;
    }

    /**
     * Files referenced by the last conversion.
     *
     * @return array<string,string> path inside the import archive's "data/" folder => absolute source path
     */
    public function getAttachments(): array {

        return $this->attachments;
    }

    /**
     * Media references of the last conversion that could not be found on disk.
     *
     * @return array<int,array{file:string,day:string,time:string,user:string}>
     */
    public function getMissingMedia(): array {

        return $this->missingMedia;
    }

    /**
     * Number of media references found in the chat during the last conversion.
     */
    public function getMediaReferenceCount(): int {

        return $this->mediaReferences;
    }

    private function endsWith(string $haystack, string $needle): bool {

        $length = strlen($needle);
        if (!$length) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }

    private function toUnixTime(string $day, string $time): int {

        // dd.mm.yyyy + hh:mi[:ss]
        $format = strlen($time) > 5 ? 'd.m.Y H:i:s' : 'd.m.Y H:i';
        $date = \DateTime::createFromFormat('!' . $format, $day . ' ' . $time, $this->timezone);
        if ($date === false) {
            throw new \InvalidArgumentException("Invalid date/time: $day $time");
        }
        return $date->getTimestamp() * 1000;
    }

}
?>
