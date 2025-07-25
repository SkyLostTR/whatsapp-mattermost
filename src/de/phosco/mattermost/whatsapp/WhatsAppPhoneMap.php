<?php
declare(strict_types = 1);

namespace de\phosco\mattermost\whatsapp;

class WhatsAppPhoneMap {

    private $map;

    public function __construct() {

        $this->map = array();
    }

    public function add(string $waPhone, string $mmUser): void {

        $this->map[$waPhone] = $mmUser;
    }

    public function get(string $waPhone): ?string {

        if (isset($this->map[$waPhone])) {
            return $this->map[$waPhone];
        }
        
        // Return a default user instead of throwing an exception
        error_log("Warning: Unknown phone number " . $waPhone . " - using default user");
        return "unknown-user";
    }

    public function count(): int {

        return count($this->map);
    }
}
?>
