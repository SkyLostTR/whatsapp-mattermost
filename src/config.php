<?php
use Dotenv\Dotenv;

// Load environment variables from .env if present
$root = dirname(__DIR__);
if (file_exists($root.'/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

/**
 * Retrieve environment variable with optional default.
 */
function env(string $key, $default = null) {
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

/**
 * Parse mapping strings of the form "\"Display Name\"=username;..." into an associative array.
 */
function parseMappings(string $mappings): array {
    $result = [];
    foreach (array_filter(array_map('trim', explode(';', $mappings))) as $pair) {
        if (preg_match('/^"?(.*?)"?=\"?(.*?)"?$/', $pair, $m)) {
            $result[$m[1]] = $m[2];
        }
    }
    return $result;
}

/**
 * Validate required environment configuration values.
 * Returns array of error messages if any required variable is missing.
 */
function validateEnvironment(): array {
    $required = [
        'MATTERMOST_URL',
        'MATTERMOST_API_TOKEN',
        'MATTERMOST_TEAM_NAME',
        'MATTERMOST_CHANNEL_NAME',
        'WHATSAPP_CHAT_FILE',
        'IMPORT_ZIP_PATH',
    ];

    $errors = [];
    foreach ($required as $var) {
        if (!env($var)) {
            $errors[] = "Missing environment variable: $var";
        }
    }

    return $errors;
}
?>
