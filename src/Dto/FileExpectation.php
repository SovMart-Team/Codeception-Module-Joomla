<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class FileExpectation
{
    /** @param list<string> $contentContains @param list<string> $contentNotContains */
    public function __construct(
        public string $root,
        public string $relativePath,
        public ?int $size = null,
        public ?string $sha256 = null,
        public ?string $exactContent = null,
        public array $contentContains = [],
        public array $contentNotContains = [],
        public ?string $mime = null,
        public ?int $imageWidth = null,
        public ?int $imageHeight = null,
    ) {
    }
}
