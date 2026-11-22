<?php
declare(strict_types = 1);

namespace de\phosco\mattermost\whatsapp;

/**
 * Maps the file names referenced in a WhatsApp chat export to the files that
 * actually exist on disk.
 *
 * WhatsApp exports are frequently unpacked, copied or re-zipped by other tools,
 * which changes the exact spelling of the file names: files end up in
 * sub folders ("Media/WhatsApp Images/..."), get a different letter case,
 * a different Unicode normalisation (NFD on macOS), or invisible direction
 * marks. The resolver indexes the whole media directory recursively and
 * matches names from the most to the least strict rule.
 */
class MediaResolver {

    /** Bidi / zero-width characters WhatsApp sprinkles into exported text. */
    private const INVISIBLE_CHARS = '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{FEFF}]/u';

    /** @var string */
    private $baseDir;

    /** @var array<string,string> relative path => absolute path */
    private $files = array();

    /** @var array<string,string[]> normalised relative path => relative paths */
    private $byPath = array();

    /** @var array<string,string[]> exact (normalised) basename => relative paths */
    private $byName = array();

    /** @var array<string,string[]> lower-cased basename => relative paths */
    private $byLowerName = array();

    /** @var array<string,string[]> lower-cased basename without extension => relative paths */
    private $byStem = array();

    /**
     * @param string   $baseDir      Directory containing the exported media (searched recursively)
     * @param string[] $excludeFiles Absolute paths that must never be treated as media (e.g. the chat text file)
     */
    public function __construct(string $baseDir, array $excludeFiles = array()) {

        $this->baseDir = rtrim($baseDir, "/\\");

        $exclude = array();
        foreach ($excludeFiles as $file) {
            $real = realpath($file);
            if ($real !== false) {
                $exclude[$real] = true;
            }
        }

        if ($this->baseDir !== '' && is_dir($this->baseDir)) {
            $this->index($exclude);
        }
    }

    private function index(array $exclude): void {

        $iterator = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                        new \RecursiveDirectoryIterator($this->baseDir,
                                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
                        function (\SplFileInfo $file): bool {
                            $name = $file->getFilename();
                            // skip hidden files, macOS resource forks and zip metadata folders
                            return $name[0] !== '.' && $name !== '__MACOSX';
                        }));

        $baseLength = strlen($this->baseDir) + 1;
        $paths = array();
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $absolute = $file->getPathname();
            $real = realpath($absolute);
            if ($real !== false && isset($exclude[$real])) {
                continue;
            }
            $paths[] = $absolute;
        }

        // Deterministic order: shallow paths first, then alphabetical.
        usort($paths, function (string $a, string $b): int {
            $depth = substr_count($a, DIRECTORY_SEPARATOR) <=> substr_count($b, DIRECTORY_SEPARATOR);
            return $depth !== 0 ? $depth : strcmp($a, $b);
        });

        foreach ($paths as $absolute) {
            $relative = str_replace('\\', '/', substr($absolute, $baseLength));
            $relative = self::normalize($relative);
            $name = basename($relative);

            $this->files[$relative] = $absolute;
            $this->byPath[$relative][] = $relative;
            $this->byName[$name][] = $relative;
            $this->byLowerName[self::lower($name)][] = $relative;
            $this->byStem[self::lower(self::stem($name))][] = $relative;
        }
    }

    /**
     * Strip invisible characters, surrounding whitespace and convert to Unicode NFC.
     */
    public static function normalize(string $name): string {

        $clean = preg_replace(self::INVISIBLE_CHARS, '', $name);
        if ($clean === null) {
            // not valid UTF-8; keep the raw bytes
            $clean = $name;
        }
        $clean = trim($clean);

        if (class_exists('\Normalizer')) {
            $nfc = \Normalizer::normalize($clean, \Normalizer::FORM_C);
            if (is_string($nfc)) {
                $clean = $nfc;
            }
        }
        return $clean;
    }

    private static function lower(string $value): string {

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function stem(string $name): string {

        $dot = strrpos($name, '.');
        return $dot === false || $dot === 0 ? $name : substr($name, 0, $dot);
    }

    /**
     * Find the file referenced in the chat export.
     *
     * @return string|null Path relative to the media directory (always using "/"), or null if not found
     */
    public function resolve(string $reference): ?string {

        $reference = str_replace('\\', '/', self::normalize($reference));
        if ($reference === '') {
            return null;
        }
        $name = basename($reference);

        // 1. exact relative path, 2. exact file name anywhere in the tree
        foreach (array($this->byPath[$reference] ?? array(), $this->byName[$name] ?? array()) as $candidates) {
            if (count($candidates) > 0) {
                return $candidates[0];
            }
        }

        // 3. case-insensitive file name (e.g. IMG-...JPG vs IMG-...jpg)
        $candidates = $this->byLowerName[self::lower($name)] ?? array();
        if (count($candidates) > 0) {
            return $candidates[0];
        }

        // 4. same name with a different extension (e.g. .jpeg vs .jpg, .opus vs .ogg) - only if unambiguous
        $candidates = $this->byStem[self::lower(self::stem($name))] ?? array();
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        return null;
    }

    public function getAbsolutePath(string $relativePath): ?string {

        return $this->files[$relativePath] ?? null;
    }

    public function getBaseDir(): string {

        return $this->baseDir;
    }

    public function count(): int {

        return count($this->files);
    }
}
?>
