<?php
declare(strict_types=1);

use Dotenv\Dotenv;

// Load environment variables from project root if .env exists
$rootDir = dirname(__DIR__);
if (class_exists(Dotenv::class)) {
    $dotenv = Dotenv::createImmutable($rootDir);
    $dotenv->safeLoad();
}

/**
 * Get an environment variable with optional default value.
 *
 * @param string $key     The environment variable name
 * @param mixed  $default Default value when variable is missing
 *
 * @return mixed
 */
function env(string $key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

/**
 * Parse semicolon separated mappings of the form "key"="value".
 *
 * @param string $mappings Mapping string from the environment
 *
 * @return array<string,string>
 */
function parseMappings(string $mappings): array
{
    $result = [];
    $mappings = trim($mappings);
    if ($mappings === '') {
        return $result;
    }

    $pairs = array_filter(array_map('trim', explode(';', $mappings)));
    foreach ($pairs as $pair) {
        if (preg_match('/^\"(.+?)\"=\"(.+?)\"$/', $pair, $matches)) {
            $result[$matches[1]] = $matches[2];
        }
    }
    return $result;
}

/**
 * Validate required environment configuration.
 *
 * @return array<int,string> List of error messages
 */
function validateEnvironment(): array
{
    $errors = [];

    $required = [
        'WHATSAPP_CHAT_FILE',
        'IMPORT_ZIP_PATH',
        'MATTERMOST_URL',
        'MATTERMOST_API_TOKEN',
        'MATTERMOST_TEAM_NAME',
        'MATTERMOST_CHANNEL_NAME',
    ];

    foreach ($required as $key) {
        $value = env($key);
        if ($value === null || trim((string) $value) === '') {
            $errors[] = "$key is not set or empty";
            continue;
        }
        if ($key === 'WHATSAPP_CHAT_FILE' && !file_exists($value)) {
            $errors[] = "WHATSAPP_CHAT_FILE not found at $value";
        }
    }

    if (env('MATTERMOST_URL') === 'https://your-mattermost-server.com') {
        $errors[] = 'MATTERMOST_URL is set to placeholder value';
    }

    if (env('MATTERMOST_API_TOKEN') === 'your-api-token-here') {
        $errors[] = 'MATTERMOST_API_TOKEN is set to placeholder value';
    }

    return $errors;
}

