<?php

declare(strict_types = 1);

namespace de\phosco\mattermost\whatsapp;

class Mention {

    private $username;

    public function __construct(string $username) {
        $this->username = $username;
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function __toString(): string {
        return $this->username;
    }
}
