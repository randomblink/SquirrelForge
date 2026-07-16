<?php

declare(strict_types=1);

$root = realpath($argv[1] ?? '.');
if ($root === false) {
    fwrite(STDERR, "Repository root does not exist.\n");
    exit(2);
}

$issues = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $item): bool {
            if ($item->isDir() && in_array($item->getFilename(), ['.git', 'vendor'], true)) {
                return false;
            }
            return true;
        }
    )
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
        continue;
    }

    $contents = file_get_contents($file->getPathname());
    if ($contents === false) {
        $issues[] = [$file->getPathname(), 0, 'could not read file'];
        continue;
    }

    // Examples inside fenced or inline code are text, not navigable links.
    $contents = preg_replace('/```.*?```/s', '', $contents) ?? $contents;
    $contents = preg_replace('/`[^`\n]*`/', '', $contents) ?? $contents;

    foreach (preg_split('/\R/', $contents) ?: [] as $index => $line) {
        preg_match_all('/\[[^\]]*\]\(([^)]+)\)/', $line, $matches);
        foreach ($matches[1] as $destination) {
            if (preg_match('/^(?:https?:|mailto:|#)/i', $destination)) {
                continue;
            }

            $path = explode('#', trim($destination, '<>'), 2)[0];
            if ($path === '') {
                continue;
            }

            $target = $file->getPath() . DIRECTORY_SEPARATOR . rawurldecode($path);
            if (!file_exists($target)) {
                $relativeFile = ltrim(substr($file->getPathname(), strlen($root)), DIRECTORY_SEPARATOR);
                $issues[] = [$relativeFile, $index + 1, $destination];
            }
        }
    }
}

if ($issues !== []) {
    foreach ($issues as [$file, $line, $destination]) {
        echo "BROKEN: {$file}:{$line} -> {$destination}\n";
    }
    exit(1);
}

echo "All local Markdown links resolve.\n";
