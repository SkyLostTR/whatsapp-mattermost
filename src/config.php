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
 * Interpret an environment value as boolean ("1", "true", "yes", "on").
 */
function envBool(string $key, bool $default = false): bool
{
    $value = env($key);
    if ($value === null) {
        return $default;
    }
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

/**
 * Validate the effective configuration.
 *
 * @param array<string,mixed> $config     Keys: chat_file, output, team, channel, url, token
 * @param bool                $requireApi Whether Mattermost API credentials are needed
 *
 * @return array<int,string> List of error messages
 */
function validateConfig(array $config, bool $requireApi): array
{
    $errors = [];

    $required = [
        'chat_file' => 'WHATSAPP_CHAT_FILE (--input)',
        'output'    => 'IMPORT_ZIP_PATH (--output)',
        'team'      => 'MATTERMOST_TEAM_NAME (--team)',
        'channel'   => 'MATTERMOST_CHANNEL_NAME (--channel)',
    ];
    if ($requireApi) {
        $required['url'] = 'MATTERMOST_URL';
        $required['token'] = 'MATTERMOST_API_TOKEN';
    }

    foreach ($required as $key => $label) {
        $value = $config[$key] ?? null;
        if ($value === null || trim((string) $value) === '') {
            $errors[] = "$label is not set or empty";
        }
    }

    if (!empty($config['chat_file']) && !file_exists($config['chat_file'])) {
        $errors[] = "WhatsApp chat export not found at {$config['chat_file']}";
    }

    if (!empty($config['media_dir']) && !is_dir($config['media_dir'])) {
        $errors[] = "Media folder not found at {$config['media_dir']}";
    }

    if ($requireApi) {
        if (($config['url'] ?? null) === 'https://your-mattermost-server.com') {
            $errors[] = 'MATTERMOST_URL is set to placeholder value';
        }
        if (($config['token'] ?? null) === 'your-api-token-here') {
            $errors[] = 'MATTERMOST_API_TOKEN is set to placeholder value';
        }
    }

    return $errors;
}

/**
 * Validate required environment configuration (all settings, including API credentials).
 *
 * @return array<int,string> List of error messages
 */
function validateEnvironment(): array
{
    return validateConfig([
        'chat_file' => env('WHATSAPP_CHAT_FILE'),
        'media_dir' => env('WHATSAPP_MEDIA_DIR'),
        'output'    => env('IMPORT_ZIP_PATH'),
        'team'      => env('MATTERMOST_TEAM_NAME'),
        'channel'   => env('MATTERMOST_CHANNEL_NAME'),
        'url'       => env('MATTERMOST_URL'),
        'token'     => env('MATTERMOST_API_TOKEN'),
    ], true);
}
