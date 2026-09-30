<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Dto\FilesystemBaseline;
use JoomlaCodeception\Dto\FixtureFile;

final readonly class FileFixtureManager
{
    /** @param array<string, string> $roots */
    public function __construct(
        private array $roots,
        private string $publicImagePrefix,
        private CleanupJournal $cleanupJournal,
    ) {
    }

    /** @param list<FixtureFile> $files */
    public function materialize(array $files, FixtureProvider $provider): void
    {
        foreach ($files as $file) {
            if ($file->root === 'fixture') {
                continue;
            }

            if ($file->relativePath === null) {
                throw new \RuntimeException(sprintf('Prepared file %s has no target relative path.', $file->source));
            }

            $source           = $provider->fixturePath($file->source);
            $destination      = $this->path($file->root, $file->relativePath);
            $directory        = dirname($destination);
            $directoryCreated = !is_dir($directory);

            if ($directoryCreated && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Cannot create fixture directory: %s', $directory));
            }

            if (!copy($source, $destination)) {
                throw new \RuntimeException(sprintf('Cannot create fixture file: %s', $destination));
            }

            if ($directoryCreated) {
                chmod($directory, 0777);
            }

            chmod($destination, 0666);
            $this->cleanupJournal->register('file ' . $destination, static function () use ($destination): void {
                if (is_file($destination) && !unlink($destination)) {
                    throw new \RuntimeException(sprintf('Cannot remove fixture file: %s', $destination));
                }
            });
        }
    }

    public function path(string $root, string $relativePath): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');

        if ($relativePath === '' || str_contains($relativePath, "\0") || preg_match('#(^|/)\.\.(/|$)#', $relativePath)) {
            throw new \RuntimeException(sprintf('Unsafe fixture path: %s', $relativePath));
        }

        $rootPath = $this->root($root);
        $this->assertNoSymlink($rootPath, $relativePath);

        return $rootPath . '/' . $relativePath;
    }

    /** @return array<string, string> */
    public function snapshot(): array
    {
        $snapshot = [];

        foreach (['images', 'storage', 'tmp'] as $alias) {
            $root = $this->root($alias);

            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS));

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isLink()) {
                    $snapshot[$file->getPathname()] = 'link:' . (readlink($file->getPathname()) ?: '');
                } elseif ($file->isFile()) {
                    $snapshot[$file->getPathname()] = hash_file('sha256', $file->getPathname()) ?: '';
                }
            }
        }

        ksort($snapshot);

        return $snapshot;
    }

    public function captureBaseline(): FilesystemBaseline
    {
        $backupRoot = rtrim(sys_get_temp_dir(), '/') . '/joomla-codeception-baseline-' . bin2hex(random_bytes(12));

        if (!mkdir($backupRoot, 0700, true) && !is_dir($backupRoot)) {
            throw new \RuntimeException(sprintf('Cannot create filesystem baseline directory: %s', $backupRoot));
        }

        $files       = [];
        $directories = [];
        $links       = [];

        try {
            foreach (['images', 'storage', 'tmp'] as $alias) {
                $root = $this->root($alias);

                if (!file_exists($root) && !is_link($root)) {
                    continue;
                }

                if (is_link($root)) {
                    throw new \RuntimeException(sprintf('Filesystem root cannot be a symlink: %s', $root));
                }

                $directories[$root] = fileperms($root) & 0777;
                $iterator           = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST,
                );

                /** @var \SplFileInfo $entry */
                foreach ($iterator as $entry) {
                    $path = $entry->getPathname();

                    if ($entry->isLink()) {
                        $target = readlink($path);

                        if (!is_string($target)) {
                            throw new \RuntimeException(sprintf('Cannot read baseline symlink: %s', $path));
                        }

                        $links[$path] = $target;

                        continue;
                    }

                    if ($entry->isDir()) {
                        $directories[$path] = $entry->getPerms() & 0777;

                        continue;
                    }

                    if (!$entry->isFile()) {
                        continue;
                    }

                    $backup = $backupRoot . '/' . hash('sha256', $path);

                    if (!copy($path, $backup)) {
                        throw new \RuntimeException(sprintf('Cannot back up baseline file: %s', $path));
                    }

                    $files[$path] = ['backup' => $backup, 'mode' => $entry->getPerms() & 0777];
                }
            }
        } catch (\Throwable $throwable) {
            $this->removeTree($backupRoot);

            throw $throwable;
        }

        ksort($files);
        ksort($directories);
        ksort($links);

        return new FilesystemBaseline($backupRoot, $files, $directories, $links);
    }

    public function restoreBaseline(FilesystemBaseline $baseline): void
    {
        try {
            $current         = $this->inventory();
            $baselineEntries = array_fill_keys([
                ...array_keys($baseline->files),
                ...array_keys($baseline->directories),
                ...array_keys($baseline->links),
            ], true);

            foreach ([...$current['files'], ...$current['links']] as $path) {
                if (!isset($baselineEntries[$path]) && $this->insideRoot($path) && !unlink($path)) {
                    throw new \RuntimeException(sprintf('Cannot remove method-owned filesystem entry: %s', $path));
                }
            }

            $createdDirectories = array_values(array_filter(
                $current['directories'],
                static fn (string $path): bool => !isset($baselineEntries[$path]),
            ));
            usort($createdDirectories, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

            foreach ($createdDirectories as $directory) {
                if ($this->insideRoot($directory) && is_dir($directory) && !rmdir($directory)) {
                    throw new \RuntimeException(sprintf('Cannot remove method-owned directory: %s', $directory));
                }
            }

            $directories = array_keys($baseline->directories);
            usort($directories, static fn (string $left, string $right): int => strlen($left) <=> strlen($right));

            foreach ($directories as $directory) {
                if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) {
                    $this->removeEntry($directory);
                }

                if (!is_dir($directory) && !mkdir($directory, $baseline->directories[$directory], true) && !is_dir($directory)) {
                    throw new \RuntimeException(sprintf('Cannot restore baseline directory: %s', $directory));
                }

                chmod($directory, $baseline->directories[$directory]);
            }

            foreach ($baseline->files as $path => $metadata) {
                if (is_link($path) || (file_exists($path) && !is_file($path))) {
                    $this->removeEntry($path);
                }

                if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
                    throw new \RuntimeException(sprintf('Cannot recreate baseline parent directory: %s', dirname($path)));
                }

                if (!copy($metadata['backup'], $path)) {
                    throw new \RuntimeException(sprintf('Cannot restore baseline file: %s', $path));
                }

                chmod($path, $metadata['mode']);
            }

            foreach ($baseline->links as $path => $target) {
                if (file_exists($path) || is_link($path)) {
                    $this->removeEntry($path);
                }

                if (!symlink($target, $path)) {
                    throw new \RuntimeException(sprintf('Cannot restore baseline symlink: %s', $path));
                }
            }
        } catch (\Throwable $throwable) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot restore filesystem baseline; backup preserved at %s: %s',
                    $baseline->backupRoot,
                    $throwable->getMessage(),
                ),
                0,
                $throwable,
            );
        }

        $this->removeTree($baseline->backupRoot);
    }

    public function publicImagePath(string $url): string
    {
        $prefix = '/' . trim($this->publicImagePrefix, '/') . '/';
        $url    = '/' . ltrim($url, '/');

        if (!str_starts_with($url, $prefix)) {
            throw new \RuntimeException(sprintf('Image URL is outside the fixture root: %s', $url));
        }

        return $this->path('images', substr($url, strlen($prefix)));
    }

    private function root(string $alias): string
    {
        $root = $this->roots[$alias] ?? null;

        if (!is_string($root) || $root === '') {
            throw new \RuntimeException(sprintf('Unknown filesystem root alias: %s', $alias));
        }

        return rtrim($root, '/');
    }

    private function insideRoot(string $path): bool
    {
        foreach (['images', 'storage', 'tmp'] as $alias) {
            $root = rtrim($this->root($alias), '/');

            if ($path === $root || str_starts_with($path, $root . '/')) {
                return true;
            }
        }

        return false;
    }

    /** @return array{files: list<string>, directories: list<string>, links: list<string>} */
    private function inventory(): array
    {
        $files       = [];
        $directories = [];
        $links       = [];

        foreach (['images', 'storage', 'tmp'] as $alias) {
            $root = $this->root($alias);

            if (!file_exists($root) && !is_link($root)) {
                continue;
            }

            if (is_link($root)) {
                $links[] = $root;

                continue;
            }

            $directories[] = $root;
            $iterator      = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );

            /** @var \SplFileInfo $entry */
            foreach ($iterator as $entry) {
                if ($entry->isLink()) {
                    $links[] = $entry->getPathname();
                } elseif ($entry->isDir()) {
                    $directories[] = $entry->getPathname();
                } elseif ($entry->isFile()) {
                    $files[] = $entry->getPathname();
                }
            }
        }

        return compact('files', 'directories', 'links');
    }

    private function assertNoSymlink(string $root, string $relativePath): void
    {
        if (is_link($root)) {
            throw new \RuntimeException(sprintf('Filesystem root cannot be a symlink: %s', $root));
        }

        $path = rtrim($root, '/');

        foreach (explode('/', $relativePath) as $segment) {
            $path .= '/' . $segment;

            if (is_link($path)) {
                throw new \RuntimeException(sprintf('Fixture path contains a symlink: %s', $path));
            }
        }
    }

    private function removeEntry(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            if (!unlink($path)) {
                throw new \RuntimeException(sprintf('Cannot remove filesystem entry: %s', $path));
            }

            return;
        }

        if (is_dir($path)) {
            $this->removeTree($path);
        }
    }

    private function removeTree(string $directory): void
    {
        if (!file_exists($directory) && !is_link($directory)) {
            return;
        }

        if (is_link($directory) || is_file($directory)) {
            unlink($directory);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isLink() || $entry->isFile()) {
                unlink($entry->getPathname());
            } else {
                rmdir($entry->getPathname());
            }
        }

        rmdir($directory);
    }

}
