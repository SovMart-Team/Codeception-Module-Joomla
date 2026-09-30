<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class DatabaseCountExpectation
{
    /** @param array<string, mixed> $criteria */
    public function __construct(
        public string $table,
        public array $criteria,
        public int $count,
        public int $timeoutMilliseconds = 0,
    ) {
    }
}
