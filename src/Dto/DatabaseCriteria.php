<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class DatabaseCriteria
{
    /** @param array<string, mixed> $criteria */
    public function __construct(
        public string $table,
        public array $criteria,
        public int $timeoutMilliseconds = 0,
    ) {
    }
}
