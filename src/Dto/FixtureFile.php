<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class FixtureFile
{
    public function __construct(
        public string $source,
        public string $root = 'fixture',
        public ?string $relativePath = null,
    ) {
    }
}
