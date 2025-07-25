#!/usr/bin/php
<?php
declare(strict_types = 1);

use de\phosco\mattermost\whatsapp\WhatsAppChat;
use de\phosco\mattermost\whatsapp\JsonLConverter;
use de\phosco\mattermost\whatsapp\WhatsAppUserMap;
use de\phosco\mattermost\whatsapp\WhatsAppPhoneMap;
use de\phosco\mattermost\whatsapp\WhatsAppEmojiMap;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/config.php";

// Validate environment configuration
$errors = validateEnvironment();
if (!empty($errors)) {
    echo "Configuration errors found:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
    echo "\nPlease check your .env file configuration.\n";
    exit(1);
}

function write2disk(string $file, string $json): void {

    $handle = fopen($file, "w");
    fputs($handle, $json);
    fclose($handle);
}

function importToMattermost(string $jsonlData, string $mattermostUrl, string $token, string $mediaDir = ""): bool {
    $url = rtrim($mattermostUrl, '/') . '/api/v4/imports';
    
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        echo "ZipArchive not available. Cannot create zip for import.\n";
        echo "Please install the PHP zip extension or create the import package manually.\n";
        return false;
    }
    
    // Create a temporary zip file for the import
    $tempZip = tempnam(sys_get_temp_dir(), 'mattermost_import_') . '.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($tempZip, ZipArchive::CREATE) !== TRUE) {
        echo "Failed to create zip file for import\n";
        return false;
    }
    
    // Add the JSONL data to the zip
    $zip->addFromString('data.jsonl', $jsonlData);
    
    // If media directory is provided, add media files to the data folder
    if (!empty($mediaDir) && is_dir($mediaDir)) {
        $files = scandir($mediaDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_file($mediaDir . '/' . $file)) {
                // Only add common media file types
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webp', 'pdf', 'doc', 'docx'])) {
                    $zip->addFile($mediaDir . '/' . $file, 'data/' . $file);
                }
            }
        }
    }
    
    $zip->close();
    
    $headers = [
        'Authorization: Bearer ' . $token,
    ];
    
    $postData = [
        'file' => new CURLFile($tempZip, 'application/zip', 'import.zip'),
        'filesize' => filesize($tempZip),
        'importFrom' => 'slack' // Use slack format as it's compatible
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Clean up temp file
    unlink($tempZip);
    
    if ($httpCode === 200 || $httpCode === 201) {
        echo "Import successful!\n";
        echo "Response: $response\n";
        return true;
    } else {
        echo "Import failed. HTTP Code: $httpCode\n";
        if ($error) {
            echo "cURL Error: $error\n";
        }
        echo "Response: $response\n";
        return false;
    }
}

function importPostsDirectly(array $posts, string $mattermostUrl, string $token, string $teamName, string $channelName): bool {
    $baseUrl = rtrim($mattermostUrl, '/') . '/api/v4';
    
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];
    
    // Get team ID
    $teamUrl = $baseUrl . '/teams/name/' . $teamName;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $teamUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $teamResponse = curl_exec($ch);
    $teamHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($teamHttpCode !== 200) {
        echo "Failed to get team ID. HTTP Code: $teamHttpCode\n";
        return false;
    }
    
    $teamData = json_decode($teamResponse, true);
    $teamId = $teamData['id'];
    
    // Get channel ID
    $channelUrl = $baseUrl . '/teams/' . $teamId . '/channels/name/' . $channelName;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $channelUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $channelResponse = curl_exec($ch);
    $channelHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($channelHttpCode !== 200) {
        echo "Failed to get channel ID. HTTP Code: $channelHttpCode\n";
        return false;
    }
    
    $channelData = json_decode($channelResponse, true);
    $channelId = $channelData['id'];
    
    // Import posts one by one
    $postsUrl = $baseUrl . '/posts';
    $successCount = 0;
    $totalPosts = count($posts);
    
    foreach ($posts as $index => $postData) {
        if ($postData['type'] === 'version' && $postData['version'] === '1.1.0') {
            continue; // Skip version entries
        }
        
        $post = $postData['post'];
        $postPayload = [
            'channel_id' => $channelId,
            'message' => $post['message'],
            'create_at' => $post['create_at']
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $postsUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postPayload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $postResponse = curl_exec($ch);
        $postHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($postHttpCode === 200 || $postHttpCode === 201) {
            $successCount++;
            echo "Posted " . ($index + 1) . "/$totalPosts messages\r";
        } else {
            echo "\nFailed to post message " . ($index + 1) . ". HTTP Code: $postHttpCode\n";
            echo "Response: $postResponse\n";
        }
        
        // Small delay to avoid rate limiting
        usleep(100000); // 0.1 second
    }
    
    echo "\nSuccessfully imported $successCount out of $totalPosts posts.\n";
    return $successCount > 0;
}

function createImportZip(string $zipPath, string $jsonlData, string $mediaDir = ""): bool {
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        echo "ZipArchive not available. Creating folder structure instead...\n";
        return createImportFolder($zipPath, $jsonlData, $mediaDir);
    }
    
    $zip = new ZipArchive();
    
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        echo "Failed to create zip file: $zipPath\n";
        return false;
    }
    
    // Add the JSONL data to the zip
    $zip->addFromString('data.jsonl', $jsonlData);
    
    // If media directory is provided, add media files to the data folder
    if (!empty($mediaDir) && is_dir($mediaDir)) {
        $files = scandir($mediaDir);
        $addedFiles = 0;
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_file($mediaDir . '/' . $file)) {
                // Only add common media file types
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webp', 'pdf', 'doc', 'docx', 'opus', 'aac', 'm4a'])) {
                    if ($zip->addFile($mediaDir . '/' . $file, 'data/' . $file)) {
                        $addedFiles++;
                    }
                }
            }
        }
        echo "Added $addedFiles media files to the zip\n";
    }
    
    $zip->close();
    return true;
}

function createImportFolder(string $basePath, string $jsonlData, string $mediaDir = ""): bool {
    // Remove .zip extension if present and create folder
    $folderPath = preg_replace('/\.zip$/', '', $basePath);
    
    if (!is_dir($folderPath)) {
        if (!mkdir($folderPath, 0755, true)) {
            echo "Failed to create folder: $folderPath\n";
            return false;
        }
    }
    
    // Create data.jsonl file
    $jsonlPath = $folderPath . '/data.jsonl';
    if (file_put_contents($jsonlPath, $jsonlData) === false) {
        echo "Failed to create data.jsonl file\n";
        return false;
    }
    
    // Create data directory and copy media files
    if (!empty($mediaDir) && is_dir($mediaDir)) {
        $dataDir = $folderPath . '/data';
        if (!is_dir($dataDir)) {
            if (!mkdir($dataDir, 0755, true)) {
                echo "Failed to create data directory\n";
                return false;
            }
        }
        
        $files = scandir($mediaDir);
        $copiedFiles = 0;
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_file($mediaDir . '/' . $file)) {
                // Only copy common media file types
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webp', 'pdf', 'doc', 'docx', 'opus', 'aac', 'm4a'])) {
                    $sourcePath = $mediaDir . '/' . $file;
                    $destPath = $dataDir . '/' . $file;
                    if (copy($sourcePath, $destPath)) {
                        $copiedFiles++;
                    }
                }
            }
        }
        echo "Copied $copiedFiles media files to the data folder\n";
    }
    
    echo "Import structure created at: $folderPath\n";
    return true;
}

$user = new WhatsAppUserMap();
$userMappings = parseMappings(env('USER_MAPPINGS', ''));
foreach ($userMappings as $displayName => $username) {
    $user->add($displayName, $username);
}

$phone = new WhatsAppPhoneMap();
$phoneMappings = parseMappings(env('PHONE_MAPPINGS', ''));
foreach ($phoneMappings as $phoneNumber => $username) {
    $phone->add((string)$phoneNumber, $username);
}

// Configuration for Mattermost API (loaded from environment variables)
$mattermostUrl = env('MATTERMOST_URL');
$apiToken = env('MATTERMOST_API_TOKEN');
$teamName = env('MATTERMOST_TEAM_NAME');
$channelName = env('MATTERMOST_CHANNEL_NAME');

$converter = new JsonLConverter($teamName, $channelName);
$chat = new WhatsAppChat(env('WHATSAPP_CHAT_FILE'));

echo "Choose import method:\n";
echo "1. Direct API import (Bulk import using Mattermost import API)\n";
echo "2. Individual post import (Posts one by one via regular API)\n";
echo "3. Save to file only\n";
echo "Enter choice (1-3): ";

$choice = trim(fgets(STDIN));

switch ($choice) {
    case '1':
        echo "Attempting bulk import via Mattermost import API...\n";
        $json = $converter->toJsonL($user, $phone, new WhatsAppEmojiMap(), $chat);
        
        if ($mattermostUrl === "https://your-mattermost-server.com" || $apiToken === "your-api-token-here") {
            echo "Please configure your Mattermost URL and API token in the script first.\n";
            echo "Falling back to file save...\n";
            createImportZip(env('IMPORT_ZIP_PATH'), $json, $chat->getMediaFolder());
        } else {
            if (!importToMattermost($json, $mattermostUrl, $apiToken, $chat->getMediaFolder())) {
                echo "API import failed, saving to file as backup...\n";
                createImportZip(env('IMPORT_ZIP_PATH'), $json, $chat->getMediaFolder());
            }
        }
        break;
        
    case '2':
        echo "Attempting individual post import...\n";
        $posts = $converter->toArray($user, $phone, new WhatsAppEmojiMap(), $chat);
        
        if ($mattermostUrl === "https://your-mattermost-server.com" || $apiToken === "your-api-token-here") {
            echo "Please configure your Mattermost URL and API token in the script first.\n";
            echo "Falling back to file save...\n";
            $json = $converter->toJsonL($user, $phone, new WhatsAppEmojiMap(), $chat);
            createImportZip(env('IMPORT_ZIP_PATH'), $json, $chat->getMediaFolder());
        } else {
            if (!importPostsDirectly($posts, $mattermostUrl, $apiToken, $teamName, $channelName)) {
                echo "Direct post import failed, saving to file as backup...\n";
                $json = $converter->toJsonL($user, $phone, new WhatsAppEmojiMap(), $chat);
                createImportZip(env('IMPORT_ZIP_PATH'), $json, $chat->getMediaFolder());
            }
        }
        break;
        
    case '3':
    default:
        echo "Saving to file...\n";
        $json = $converter->toJsonL($user, $phone, new WhatsAppEmojiMap(), $chat);
        createImportZip(env('IMPORT_ZIP_PATH'), $json, $chat->getMediaFolder());
        break;
}

?>
