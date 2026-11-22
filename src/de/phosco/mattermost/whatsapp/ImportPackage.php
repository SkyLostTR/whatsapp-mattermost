<?php
declare(strict_types = 1);

namespace de\phosco\mattermost\whatsapp;

/**
 * Writes a Mattermost bulk import package.
 *
 * Layout expected by Mattermost (mmctl import process / import_process job):
 *
 *   import.zip
 *   ├── data.jsonl          JSONL at the archive root
 *   └── data/               attachment "path" values are resolved relative to this folder
 *       └── <path>
 */
class ImportPackage {

    public const JSONL_NAME = 'data.jsonl';

    public const DATA_DIR = 'data';

    /** Extensions that are already compressed - storing them is much faster than deflating. */
    private const STORED_EXTENSIONS = array(
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'mp4', 'mov', '3gp', 'mkv', 'webm', 'opus', 'ogg',
        'aac', 'm4a', 'mp3', 'amr', 'zip', 'gz', 'pdf', 'docx', 'xlsx', 'pptx'
    );

    /**
     * @param array<string,string> $attachments path inside "data/" => absolute source path
     *
     * @return int Number of attachments written
     */
    public static function createZip(string $zipPath, string $jsonl, array $attachments): int {

        if (!class_exists('\ZipArchive')) {
            throw new \RuntimeException("The PHP zip extension is not available");
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Failed to create zip file: $zipPath");
        }

        $zip->addFromString(self::JSONL_NAME, $jsonl);

        $added = 0;
        foreach ($attachments as $relative => $source) {
            if (!is_file($source) || !is_readable($source)) {
                error_log("[Warning] Media file is not readable: $source");
                continue;
            }
            $entry = self::DATA_DIR . '/' . $relative;
            if (!$zip->addFile($source, $entry)) {
                error_log("[Warning] Could not add media file to zip: $source");
                continue;
            }
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
            if (in_array($extension, self::STORED_EXTENSIONS, true) && method_exists($zip, 'setCompressionName')) {
                $zip->setCompressionName($entry, \ZipArchive::CM_STORE);
            }
            $added++;
        }

        if (!$zip->close()) {
            throw new \RuntimeException("Failed to write zip file: $zipPath");
        }
        return $added;
    }

    /**
     * Same layout as {@see createZip()}, written as plain folder (when the zip extension is missing).
     *
     * @param array<string,string> $attachments path inside "data/" => absolute source path
     *
     * @return int Number of attachments written
     */
    public static function createFolder(string $folderPath, string $jsonl, array $attachments): int {

        if (!is_dir($folderPath) && !mkdir($folderPath, 0755, true)) {
            throw new \RuntimeException("Failed to create folder: $folderPath");
        }
        if (file_put_contents($folderPath . '/' . self::JSONL_NAME, $jsonl) === false) {
            throw new \RuntimeException("Failed to write " . self::JSONL_NAME);
        }

        $copied = 0;
        foreach ($attachments as $relative => $source) {
            $target = $folderPath . '/' . self::DATA_DIR . '/' . $relative;
            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                error_log("[Warning] Could not create folder $dir");
                continue;
            }
            if (!copy($source, $target)) {
                error_log("[Warning] Could not copy media file $source");
                continue;
            }
            $copied++;
        }
        return $copied;
    }
}
?>
