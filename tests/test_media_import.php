<?php
declare(strict_types = 1);

/**
 * Regression tests for media import (issue #2).
 *
 * Run: php tests/test_media_import.php
 */
require_once __DIR__ . "/../vendor/autoload.php";

use de\phosco\mattermost\whatsapp\ImportPackage;
use de\phosco\mattermost\whatsapp\JsonLConverter;
use de\phosco\mattermost\whatsapp\MediaResolver;
use de\phosco\mattermost\whatsapp\Post;
use de\phosco\mattermost\whatsapp\WhatsAppChat;
use de\phosco\mattermost\whatsapp\WhatsAppEmojiMap;
use de\phosco\mattermost\whatsapp\WhatsAppPhoneMap;
use de\phosco\mattermost\whatsapp\WhatsAppUserMap;

ini_set('error_log', '/dev/null'); // Emoji debug output
date_default_timezone_set('UTC');

$failures = 0;
$checks = 0;

function check(bool $condition, string $message): void {

    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "  ok   $message\n";
    } else {
        $failures++;
        echo "  FAIL $message\n";
    }
}

function fixture(array $files): string {

    $dir = sys_get_temp_dir() . '/wa_test_' . bin2hex(random_bytes(4));
    foreach ($files as $path => $content) {
        $full = $dir . '/' . $path;
        if (!is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }
        file_put_contents($full, $content);
    }
    return $dir;
}

function removeDir(string $dir): void {

    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

function users(): WhatsAppUserMap {

    $map = new WhatsAppUserMap();
    $map->add('Ali Veli', 'ali');
    $map->add('Ayşe', 'ayse');
    $map->add('+90 555 111 22 33', 'mehmet');
    return $map;
}

function posts(array $json): array {

    return array_values(array_filter($json, function (array $line): bool {
        return $line['type'] === 'post';
    }));
}

$lrm = "\u{200E}";

// ---------------------------------------------------------------------------
echo "Media marker detection\n";

$cases = array(
    array("{$lrm}IMG-20230723-WA0001.jpg (dosya ekli)", 'IMG-20230723-WA0001.jpg'),
    array("{$lrm}IMG-20230723-WA0001.jpg (file attached)", 'IMG-20230723-WA0001.jpg'),
    array("IMG-20230723-WA0001.jpg (dosya ekli)", 'IMG-20230723-WA0001.jpg'),
    array("Rapor Final.v2.pdf (dosya ekli)", 'Rapor Final.v2.pdf'),
    array("{$lrm}<attached: 00000012-PHOTO-2023-07-23-14-23-00.jpg>", '00000012-PHOTO-2023-07-23-14-23-00.jpg'),
    array("{$lrm}<ekli: 00000013-AUDIO-2023-07-23-14-23-00.opus>", '00000013-AUDIO-2023-07-23-14-23-00.opus'),
    array("Merhaba nasılsın (iyi misin)", null),
    array("<Medya dahil edilmedi>", null),
    array("bunu gördün mü (lol)", null),
);
foreach ($cases as $case) {
    $media = Post::extractMedia($case[0]);
    check(($media === null ? null : $media['file']) === $case[1],
            "'" . str_replace($lrm, '<LRM>', $case[0]) . "' -> " . var_export($case[1], true));
}

// ---------------------------------------------------------------------------
echo "\nMedia resolution\n";

$nfdName = "C\u{0327}izelge.pdf"; // "Çizelge.pdf" as stored by macOS (NFD)
$dir = fixture(array(
    'chat/WhatsApp Sohbeti - Test.txt' => '',
    'chat/Media/WhatsApp Images/IMG-20230723-WA0001.JPG' => 'jpg1',
    'chat/IMG-20230723-WA0002.jpg' => 'jpg2',
    'chat/Media/WhatsApp Voice Notes/202330/PTT-20230723-WA0003.opus' => 'opus',
    'chat/Media/WhatsApp Documents/' . $nfdName => 'pdf',
    'chat/Media/WhatsApp Documents/Rapor 2023.pdf' => 'pdf2',
    'chat/.nomedia' => '',
));
$resolver = new MediaResolver($dir . '/chat', array($dir . '/chat/WhatsApp Sohbeti - Test.txt'));
check($resolver->count() === 5, "indexes media recursively, ignoring the chat file and hidden files");
check($resolver->resolve('IMG-20230723-WA0002.jpg') === 'IMG-20230723-WA0002.jpg', "exact name");
check($resolver->resolve('IMG-20230723-WA0001.jpg') === 'Media/WhatsApp Images/IMG-20230723-WA0001.JPG', "sub folder + case-insensitive");
check($resolver->resolve("{$lrm}PTT-20230723-WA0003.opus ") === 'Media/WhatsApp Voice Notes/202330/PTT-20230723-WA0003.opus', "nested folder, invisible characters");
check($resolver->resolve("Çizelge.pdf") === "Media/WhatsApp Documents/Çizelge.pdf", "Unicode NFC/NFD mismatch");
check($resolver->resolve('IMG-20230723-WA0002.jpeg') === 'IMG-20230723-WA0002.jpg', "different extension, unambiguous");
check($resolver->resolve('IMG-20230723-WA0099.jpg') === null, "missing file");
removeDir($dir);

// ---------------------------------------------------------------------------
echo "\nTurkish Android export with media (issue #2)\n";

$chat = implode("\n", array(
    "\u{FEFF}23.07.2023 14:20 - Mesajlar ve aramalar uçtan uca şifrelidir.",
    "23.07.2023 14:22 - Ali Veli: Merhaba",
    "23.07.2023 14:23 - Ali Veli: {$lrm}IMG-20230723-WA0001.jpg (dosya ekli)",
    "23.07.2023 14:23 - Ali Veli: {$lrm}IMG-20230723-WA0002.jpg (dosya ekli)",
    "Bu bir açıklama",
    "23.07.2023 14:23 - Ayşe: PTT-20230723-WA0003.opus (dosya ekli)",
    "23.07.2023 14:24 - Ayşe: {$lrm}Rapor 2023.pdf (dosya ekli)",
    "23.07.2023 14:24 - Ayşe: {$lrm}Çizelge.pdf (dosya ekli)",
    "23.07.2023 14:25 - Ali Veli: {$lrm}IMG-20230723-WA0099.jpg (dosya ekli)",
    "23.07.2023 14:26 - \u{202A}+90 555 111 22 33\u{202C}: @905551112233 bak, mail: a@b.com",
    "23.07.2023 14:27 - Ayşe: 0",
    "",
));
$dir = fixture(array(
    'export/WhatsApp Sohbeti - Test.txt' => $chat,
    'export/Media/WhatsApp Images/IMG-20230723-WA0001.JPG' => 'jpg1',
    'export/Media/WhatsApp Images/IMG-20230723-WA0002.jpg' => 'jpg2',
    'export/Media/WhatsApp Voice Notes/202330/PTT-20230723-WA0003.opus' => 'opus',
    'export/Media/WhatsApp Documents/Rapor 2023.pdf' => 'pdf',
    'export/Media/WhatsApp Documents/' . $nfdName => 'pdf2',
));

$users = users();
$users->add('System', 'system');
$phones = new WhatsAppPhoneMap();
$phones->add('905551112233', 'ayse');

$whatsApp = new WhatsAppChat($dir . '/export/WhatsApp Sohbeti - Test.txt');
$converter = new JsonLConverter('team', 'channel');
$json = $converter->toArray($users, $phones, new WhatsAppEmojiMap(), $whatsApp);
$posts = posts($json);

check(count($posts) === 10, "all 10 messages converted (got " . count($posts) . ")");

$withMedia = array_values(array_filter($posts, function (array $p): bool {
    return isset($p['post']['attachments']);
}));
$paths = array();
foreach ($withMedia as $p) {
    foreach ($p['post']['attachments'] as $a) {
        $paths[] = $a['path'];
    }
}
check($paths === array(
    'Media/WhatsApp Images/IMG-20230723-WA0001.JPG',
    'Media/WhatsApp Images/IMG-20230723-WA0002.jpg',
    'Media/WhatsApp Voice Notes/202330/PTT-20230723-WA0003.opus',
    'Media/WhatsApp Documents/Rapor 2023.pdf',
    'Media/WhatsApp Documents/Çizelge.pdf',
), "attachment paths resolved: " . json_encode($paths, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

foreach ($paths as $path) {
    if (strpos($path, 'data/') === 0) {
        check(false, "attachment path must be relative to the data/ folder: $path");
    }
}
check($converter->getMediaReferenceCount() === 6, "6 media references");
check(count($converter->getMissingMedia()) === 1 && $converter->getMissingMedia()[0]['file'] === 'IMG-20230723-WA0099.jpg',
        "missing media reported");

$byMessage = array();
foreach ($posts as $p) {
    $byMessage[] = $p['post']['message'];
}
check($withMedia[0]['post']['message'] === '', "media-only post has an empty message");
check($withMedia[1]['post']['message'] === 'Bu bir açıklama', "caption kept with its media");
check(in_array("{$lrm}IMG-20230723-WA0099.jpg (dosya ekli)", $byMessage, true) ||
        in_array("IMG-20230723-WA0099.jpg (dosya ekli)", $byMessage, true), "missing media kept as text");
check(in_array('@ayse bak, mail: a@b.com', $byMessage, true), "mentions rendered, phone number mapped");
check(in_array('0', $byMessage, true), "message '0' is not lost");
check($posts[9]['post']['user'] === 'mehmet' || $posts[8]['post']['user'] === 'mehmet', "user names with bidi markers are mapped");

$createAts = array_map(function (array $p): int {
    return $p['post']['create_at'];
}, $posts);
check(count(array_unique($createAts)) === count($createAts), "create_at values are unique");
$sorted = $createAts;
sort($sorted);
check($sorted === $createAts, "create_at values keep the chat order");
check($posts[2]['post']['create_at'] === strtotime('2023-07-23 14:23:00 UTC') * 1000, "timestamp of the first post in a minute");
check($posts[3]['post']['create_at'] === strtotime('2023-07-23 14:23:00 UTC') * 1000 + 1, "same-minute post shifted by 1ms");

echo "\nImport package layout\n";
$zipPath = $dir . '/import.zip';
$jsonl = $converter->toJsonL($users, $phones, new WhatsAppEmojiMap(), $whatsApp);
$added = ImportPackage::createZip($zipPath, $jsonl, $converter->getAttachments());
check($added === 5, "5 media files packaged");

$zip = new ZipArchive();
$zip->open($zipPath);
$entries = array();
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entries[] = $zip->getNameIndex($i);
}
check(in_array('data.jsonl', $entries, true), "data.jsonl at archive root");
// Mattermost resolves attachment paths as filepath.Join("data", path) inside the archive
foreach (explode("\n", $zip->getFromName('data.jsonl')) as $line) {
    $obj = json_decode($line, true);
    foreach ($obj['post']['attachments'] ?? array() as $a) {
        check(in_array('data/' . $a['path'], $entries, true), "archive contains data/{$a['path']}");
    }
}
check($zip->getFromName('data/Media/WhatsApp Images/IMG-20230723-WA0001.JPG') === 'jpg1', "packaged file content");
$zip->close();
removeDir($dir);

// ---------------------------------------------------------------------------
echo "\niOS export\n";

$dir = fixture(array(
    'ios/_chat.txt' => implode("\r\n", array(
        "[23.07.2023 14:23:45] Ali Veli: {$lrm}<ekli: 00000012-PHOTO-2023-07-23-14-23-45.jpg>",
        "[23.07.2023 14:23:45] Ali Veli: {$lrm}<ekli: 00000013-PHOTO-2023-07-23-14-23-45.jpg>",
        "[23.07.2023 14:24:01] Ayşe: Güzel! 👍",
    )),
    'ios/00000012-PHOTO-2023-07-23-14-23-45.jpg' => 'a',
    'ios/00000013-PHOTO-2023-07-23-14-23-45.jpg' => 'b',
));
$whatsApp = new WhatsAppChat($dir . '/ios/_chat.txt');
$converter = new JsonLConverter('team', 'channel');
$posts = posts($converter->toArray(users(), new WhatsAppPhoneMap(), new WhatsAppEmojiMap(), $whatsApp));
check(count($posts) === 3, "3 messages");
check(($posts[0]['post']['attachments'][0]['path'] ?? null) === '00000012-PHOTO-2023-07-23-14-23-45.jpg', "first photo attached");
check(($posts[1]['post']['attachments'][0]['path'] ?? null) === '00000013-PHOTO-2023-07-23-14-23-45.jpg', "second photo attached");
check($posts[0]['post']['create_at'] === strtotime('2023-07-23 14:23:45 UTC') * 1000, "seconds precision");
check($posts[1]['post']['create_at'] !== $posts[0]['post']['create_at'], "photos in the same second get distinct timestamps");
removeDir($dir);

// ---------------------------------------------------------------------------
echo "\nUS Android export (month first, 12h clock)\n";

$dir = fixture(array(
    'us/WhatsApp Chat with Test.txt' => implode("\n", array(
        "7/23/23, 2:23 PM - Ali Veli: {$lrm}IMG-20230723-WA0001.jpg (file attached)",
        "7/24/23, 12:05 AM - Ayşe: ok",
    )),
    'us/IMG-20230723-WA0001.jpg' => 'a',
));
$whatsApp = new WhatsAppChat($dir . '/us/WhatsApp Chat with Test.txt');
$posts = posts((new JsonLConverter('team', 'channel'))->toArray(users(), new WhatsAppPhoneMap(), new WhatsAppEmojiMap(), $whatsApp));
check(count($posts) === 2, "2 messages");
check($posts[0]['post']['create_at'] === strtotime('2023-07-23 14:23:00 UTC') * 1000, "2:23 PM on 7/23/23");
check($posts[1]['post']['create_at'] === strtotime('2023-07-24 00:05:00 UTC') * 1000, "12:05 AM on 7/24/23");
check(($posts[0]['post']['attachments'][0]['path'] ?? null) === 'IMG-20230723-WA0001.jpg', "media attached");
removeDir($dir);

// ---------------------------------------------------------------------------
echo "\nSeparate media folder\n";

$dir = fixture(array(
    'txt/chat.txt' => "23.07.2023 14:23 - Ali Veli: {$lrm}IMG-20230723-WA0001.jpg (dosya ekli)\n",
    'media/IMG-20230723-WA0001.jpg' => 'a',
));
$whatsApp = new WhatsAppChat($dir . '/txt/chat.txt', $dir . '/media');
$posts = posts((new JsonLConverter('team', 'channel'))->toArray(users(), new WhatsAppPhoneMap(), new WhatsAppEmojiMap(), $whatsApp));
check(($posts[0]['post']['attachments'][0]['path'] ?? null) === 'IMG-20230723-WA0001.jpg', "media found in --media folder");
removeDir($dir);

echo "\n$checks checks, $failures failures\n";
exit($failures === 0 ? 0 : 1);
