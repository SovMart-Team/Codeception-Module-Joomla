<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class DatabaseStoredFileExpectation
{
    /**
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $jsonEquals
     */
    public function __construct(
        public string $table,
        public string $field,
        public array $criteria,
        public ?string $root = null,
        public ?string $jsonPath = null,
        public ?string $valueEquals = null,
        public ?string $valuePrefix = null,
        public ?string $valueSuffix = null,
        public ?bool $fileExists = null,
        public array $jsonEquals = [],
    ) {
    }
}
