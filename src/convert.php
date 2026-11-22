#!/usr/bin/php
<?php
declare(strict_types = 1);

use de\phosco\mattermost\whatsapp\ImportPackage;
use de\phosco\mattermost\whatsapp\JsonLConverter;
use de\phosco\mattermost\whatsapp\MediaResolver;
use de\phosco\mattermost\whatsapp\WhatsAppChat;
use de\phosco\mattermost\whatsapp\WhatsAppEmojiMap;
use de\phosco\mattermost\whatsapp\WhatsAppPhoneMap;
use de\phosco\mattermost\whatsapp\WhatsAppUserMap;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/config.php";

function usage(): void {

    echo <<<TXT
Usage: php src/convert.php [options]

Every option falls back to the matching variable in .env.

  --input <file>     WhatsApp export: the chat .txt or the exported .zip     (WHATSAPP_CHAT_FILE)
  --media <dir>      Folder with the exported media, searched recursively    (WHATSAPP_MEDIA_DIR)
                     Default: the folder of the chat file
  --output <file>    Import package to write (.zip)                          (IMPORT_ZIP_PATH)
  --team <name>      Mattermost team name                                    (MATTERMOST_TEAM_NAME)
  --channel <name>   Mattermost channel name                                 (MATTERMOST_CHANNEL_NAME)
  --method <m>       1|bulk, 2|posts or 3|file. Asks interactively if omitted.
  -h, --help         Show this help

TXT;
}

/**
 * Perform a Mattermost API request.
 *
 * @param mixed $body array => JSON, string => raw body, null => no body
 *
 * @return array{0:int,1:mixed,2:string,3:string} HTTP code, decoded JSON, raw response, cURL error
 */
function mattermostRequest(array $config, string $method, string $path, $body = null, array $curlOptions = array()): array {

    $ch = curl_init(rtrim($config['url'], '/') . '/api/v4' . $path);
    $headers = array('Authorization: Bearer ' . $config['token']);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!$config['tls_verify']) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    if (is_array($body) && !isset($curlOptions[CURLOPT_POSTFIELDS])) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif (is_string($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    foreach ($curlOptions as $option => $value) {
        curl_setopt($ch, $option, $value);
    }

    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $raw = is_string($response) ? $response : '';
    return array($code, json_decode($raw, true), $raw, $error);
}

function reportApiError(string $what, array $result): void {

    list($code, $json, $raw, $error) = $result;
    echo "$what failed. HTTP Code: $code\n";
    if ($error !== '') {
        echo "cURL Error: $error\n";
    }
    echo "Response: " . (is_array($json) && isset($json['message']) ? $json['message'] : $raw) . "\n";
}

/**
 * Upload the import package and start the import job - the API equivalent of
 * "mmctl import upload" + "mmctl import process". Requires a system admin token.
 */
function importToMattermost(array $config, string $zipPath): bool {

    $size = filesize($zipPath);
    $name = basename($zipPath);

    // 1. create upload session
    $result = mattermostRequest($config, 'POST', '/uploads',
            array('type' => 'import', 'filename' => $name, 'file_size' => $size));
    if ($result[0] !== 201 && $result[0] !== 200) {
        reportApiError("Creating the import upload session", $result);
        return false;
    }
    $uploadId = $result[1]['id'];

    // 2. upload the package (streamed)
    $handle = fopen($zipPath, 'rb');
    $result = mattermostRequest($config, 'POST', '/uploads/' . $uploadId, null, array(
        CURLOPT_UPLOAD => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_INFILE => $handle,
        CURLOPT_INFILESIZE => $size
    ));
    fclose($handle);
    if ($result[0] !== 201 && $result[0] !== 200) {
        reportApiError("Uploading the import package", $result);
        return false;
    }

    // 3. start the import job
    $importFile = $uploadId . '_' . $name;
    $result = mattermostRequest($config, 'POST', '/jobs',
            array('type' => 'import_process', 'data' => array('import_file' => $importFile)));
    if ($result[0] !== 201 && $result[0] !== 200) {
        reportApiError("Starting the import job", $result);
        return false;
    }
    $jobId = $result[1]['id'];
    echo "Import job $jobId started for $importFile\n";

    // 4. wait for the job
    $status = 'pending';
    for ($i = 0; $i < 720; $i++) {
        sleep(5);
        $result = mattermostRequest($config, 'GET', '/jobs/' . $jobId);
        if ($result[0] !== 200) {
            reportApiError("Reading the import job status", $result);
            return false;
        }
        $status = $result[1]['status'] ?? 'unknown';
        echo "Import job status: $status\r";
        if (in_array($status, array('success', 'error', 'canceled'), true)) {
            break;
        }
    }
    echo "\n";

    if ($status === 'success') {
        echo "Import successful!\n";
        return true;
    }
    echo "Import job finished with status '$status'.\n";
    if (!empty($result[1]['data']['error'])) {
        echo "Error: " . $result[1]['data']['error'] . "\n";
    }
    echo "Check it with: mmctl import job show $jobId\n";
    return false;
}

/**
 * Post every message through the regular API, uploading its media first.
 * Posts appear under the account owning the token.
 */
function importPostsDirectly(array $config, array $posts, array $attachments): bool {

    $result = mattermostRequest($config, 'GET', '/teams/name/' . rawurlencode($config['team']));
    if ($result[0] !== 200) {
        reportApiError("Looking up team '{$config['team']}'", $result);
        return false;
    }
    $teamId = $result[1]['id'];

    $result = mattermostRequest($config, 'GET',
            '/teams/' . $teamId . '/channels/name/' . rawurlencode($config['channel']));
    if ($result[0] !== 200) {
        reportApiError("Looking up channel '{$config['channel']}'", $result);
        return false;
    }
    $channelId = $result[1]['id'];

    $posts = array_values(array_filter($posts, function (array $entry): bool {
        return ($entry['type'] ?? '') === 'post';
    }));

    $successCount = 0;
    $totalPosts = count($posts);

    foreach ($posts as $index => $postData) {
        $post = $postData['post'];

        $fileIds = array();
        foreach ($post['attachments'] ?? array() as $attachment) {
            $source = $attachments[$attachment['path']] ?? null;
            if ($source === null) {
                continue;
            }
            $result = mattermostRequest($config, 'POST', '/files', null, array(
                CURLOPT_POSTFIELDS => array(
                    'channel_id' => $channelId,
                    'files' => new CURLFile($source, '', basename($attachment['path']))
                )
            ));
            if (($result[0] === 201 || $result[0] === 200) && !empty($result[1]['file_infos'][0]['id'])) {
                $fileIds[] = $result[1]['file_infos'][0]['id'];
            } else {
                echo "\n";
                reportApiError("Uploading {$attachment['path']}", $result);
            }
        }

        $payload = array(
            'channel_id' => $channelId,
            'message' => $post['message'],
            'create_at' => $post['create_at']
        );
        if (count($fileIds) > 0) {
            $payload['file_ids'] = $fileIds;
        }

        $result = mattermostRequest($config, 'POST', '/posts', $payload);
        if ($result[0] === 200 || $result[0] === 201) {
            $successCount++;
            echo "Posted " . ($index + 1) . "/$totalPosts messages\r";
        } else {
            echo "\n";
            reportApiError("Posting message " . ($index + 1), $result);
        }

        // Small delay to avoid rate limiting
        usleep(100000);
    }

    echo "\nSuccessfully imported $successCount out of $totalPosts posts.\n";
    return $successCount > 0;
}

/**
 * Write the import package. Falls back to a folder when the zip extension is missing.
 *
 * @return string|null Path of the written zip, null if a folder was written or writing failed
 */
function writeImportPackage(string $zipPath, string $jsonl, array $attachments): ?string {

    try {
        if (class_exists('ZipArchive')) {
            $added = ImportPackage::createZip($zipPath, $jsonl, $attachments);
            echo "Added $added media files to $zipPath\n";
            return $zipPath;
        }
        echo "ZipArchive not available. Creating folder structure instead...\n";
        $folder = preg_replace('/\.zip$/i', '', $zipPath);
        $copied = ImportPackage::createFolder($folder, $jsonl, $attachments);
        echo "Copied $copied media files. Import structure created at: $folder\n";
        echo "Zip its contents (data.jsonl and data/) to import it with mmctl.\n";
    } catch (\RuntimeException $e) {
        echo $e->getMessage() . "\n";
    }
    return null;
}

/**
 * Extract an exported WhatsApp .zip and return the chat text file inside it.
 */
function extractChatArchive(string $zipFile): string {

    if (!class_exists('ZipArchive')) {
        throw new \RuntimeException("The PHP zip extension is required to read $zipFile");
    }
    $dir = sys_get_temp_dir() . '/whatsapp_export_' . bin2hex(random_bytes(6));
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true || !mkdir($dir, 0700) || !$zip->extractTo($dir)) {
        throw new \RuntimeException("Could not extract $zipFile");
    }
    $zip->close();

    register_shutdown_function(function () use ($dir): void {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    });

    $candidates = array();
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'txt' && strpos($file->getPathname(), '__MACOSX') === false) {
            $candidates[] = $file->getPathname();
        }
    }
    if (count($candidates) === 0) {
        throw new \RuntimeException("No chat .txt file found in $zipFile");
    }
    // iOS names it _chat.txt, Android "WhatsApp Chat with ....txt"; prefer those over attached .txt documents
    usort($candidates, function (string $a, string $b): int {
        $score = function (string $path): int {
            $name = strtolower(basename($path));
            return ($name === '_chat.txt' || strpos($name, 'whatsapp') !== false) ? 0 : 1;
        };
        return array($score($a), substr_count($a, '/'), $a) <=> array($score($b), substr_count($b, '/'), $b);
    });
    return $candidates[0];
}

// ---------------------------------------------------------------------------

$options = getopt('h', array('input:', 'media:', 'output:', 'team:', 'channel:', 'method:', 'help'));
if ($options === false || isset($options['h']) || isset($options['help'])) {
    usage();
    exit($options === false ? 1 : 0);
}

$config = array(
    'chat_file' => $options['input'] ?? env('WHATSAPP_CHAT_FILE'),
    'media_dir' => $options['media'] ?? env('WHATSAPP_MEDIA_DIR'),
    'output' => $options['output'] ?? env('IMPORT_ZIP_PATH'),
    'team' => $options['team'] ?? env('MATTERMOST_TEAM_NAME'),
    'channel' => $options['channel'] ?? env('MATTERMOST_CHANNEL_NAME'),
    'url' => env('MATTERMOST_URL'),
    'token' => env('MATTERMOST_API_TOKEN'),
    'tls_verify' => !envBool('MATTERMOST_SKIP_TLS_VERIFY', false),
    'date_order' => env('DATE_ORDER'),
    'timezone' => env('TIMEZONE')
);

$methods = array('1' => '1', 'bulk' => '1', '2' => '2', 'posts' => '2', '3' => '3', 'file' => '3');
if (isset($options['method'])) {
    $choice = $methods[strtolower((string) $options['method'])] ?? null;
    if ($choice === null) {
        echo "Unknown --method '{$options['method']}'\n";
        usage();
        exit(1);
    }
} else {
    echo "Choose import method:\n";
    echo "1. Direct API import (Bulk import using Mattermost import API, requires system admin)\n";
    echo "2. Individual post import (Posts one by one via regular API)\n";
    echo "3. Save to file only\n";
    echo "Enter choice (1-3): ";
    $input = fgets(STDIN);
    $choice = $methods[strtolower(trim($input === false ? '' : $input))] ?? '3';
}

$errors = validateConfig($config, $choice !== '3');
if (!empty($errors)) {
    echo "Configuration errors found:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
    echo "\nPlease check your .env file configuration or command line options (--help).\n";
    exit(1);
}

// The import package has to be a zip containing data.jsonl and the media
if (preg_match('/\.jsonl?$/i', $config['output'])) {
    $config['output'] = preg_replace('/\.jsonl?$/i', '.zip', $config['output']);
    echo "Note: Mattermost imports a .zip containing the JSONL and the media, writing {$config['output']}\n";
}

try {
    if (strtolower(pathinfo($config['chat_file'], PATHINFO_EXTENSION)) === 'zip') {
        $archive = $config['chat_file'];
        $config['chat_file'] = extractChatArchive($archive);
        if (empty($config['media_dir'])) {
            $config['media_dir'] = dirname($config['chat_file']);
        }
        echo "Extracted $archive\n";
    }

    $chat = new WhatsAppChat($config['chat_file'], $config['media_dir'], $config['date_order']);
    $resolver = new MediaResolver($chat->getMediaFolder(), array($chat->getChatFile()));
    $converter = new JsonLConverter($config['team'], $config['channel'], $resolver, $config['timezone']);

    $user = new WhatsAppUserMap();
    foreach (parseMappings((string) env('USER_MAPPINGS', '')) as $displayName => $username) {
        $user->add((string) $displayName, $username);
    }
    $phone = new WhatsAppPhoneMap();
    foreach (parseMappings((string) env('PHONE_MAPPINGS', '')) as $phoneNumber => $username) {
        $phone->add((string) $phoneNumber, $username);
    }

    $posts = $converter->toArray($user, $phone, new WhatsAppEmojiMap(), $chat);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

$jsonl = implode("\n", array_map(function (array $obj): string {
    return json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}, $posts));
$attachments = $converter->getAttachments();

// Media report
echo "Media folder: {$resolver->getBaseDir()} ({$resolver->count()} files)\n";
foreach ($converter->getMissingMedia() as $missing) {
    echo "[Warning] Failed to locate media file: {$missing['file']} (message from {$missing['user']} on {$missing['day']} {$missing['time']})\n";
}
$referenced = $converter->getMediaReferenceCount();
$found = $referenced - count($converter->getMissingMedia());
echo "Media references: $referenced, attached: $found, missing: " . count($converter->getMissingMedia()) . "\n";
if ($referenced > 0 && $resolver->count() === 0) {
    echo "No media files found. Pass the folder with the exported media via --media or WHATSAPP_MEDIA_DIR.\n";
}

switch ($choice) {
    case '1':
        echo "Attempting bulk import via Mattermost import API...\n";
        $zip = writeImportPackage($config['output'], $jsonl, $attachments);
        if ($zip === null) {
            echo "Bulk import needs a zip package; please upload it manually with mmctl.\n";
            exit(1);
        }
        if (!importToMattermost($config, $zip)) {
            echo "API import failed. The import package is saved at $zip\n";
            exit(1);
        }
        break;

    case '2':
        echo "Attempting individual post import...\n";
        if (!importPostsDirectly($config, $posts, $attachments)) {
            echo "Direct post import failed, saving to file as backup...\n";
            writeImportPackage($config['output'], $jsonl, $attachments);
            exit(1);
        }
        break;

    case '3':
    default:
        echo "Saving to file...\n";
        if (writeImportPackage($config['output'], $jsonl, $attachments) !== null) {
            echo "Import it with:\n";
            echo "  mmctl import upload {$config['output']}\n";
            echo "  mmctl import list available\n";
            echo "  mmctl import process <uploaded-file-name>\n";
        }
        break;
}
