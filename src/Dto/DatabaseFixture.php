<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class DatabaseFixture
{
    /** @param array<string, mixed> $values @param array<string, mixed> $criteria */
    public function __construct(
        public string $key,
        public string $table,
        public array $values,
        public MutationMode $mode = MutationMode::Insert,
        public array $criteria = [],
    ) {
    }
}
