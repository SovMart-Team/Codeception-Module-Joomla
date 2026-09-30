<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class FilesystemBaseline
{
    /**
     * @param array<string, array{backup: string, mode: int}> $files
     * @param array<string, int> $directories
     * @param array<string, string> $links
     */
    public function __construct(
        public string $backupRoot,
        public array $files,
        public array $directories,
        public array $links,
    ) {
    }
}
