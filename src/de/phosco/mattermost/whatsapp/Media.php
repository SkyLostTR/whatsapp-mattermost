<?php
declare(strict_types = 1);

namespace de\phosco\mattermost\whatsapp;

class Media implements PostContent {

    private $link;

    private $original;

    /**
     * @param string $link     File name as referenced in the chat export
     * @param string $original The original text of the media marker, used as a fallback
     *                         when the file cannot be found
     */
    public function __construct(string $link, string $original = '') {

        $this->link = $link;
        $this->original = $original !== '' ? $original : $link;
    }

    public function getContent(): string {

        return $this->link;
    }

    public function getOriginal(): string {

        return $this->original;
    }
}
?>
