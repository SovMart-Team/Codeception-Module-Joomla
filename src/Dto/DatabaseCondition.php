<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class DatabaseCondition
{
    public function __construct(
        public DatabaseOperator $operator,
        public mixed $value = null,
        public bool $semanticJson = false,
    ) {
    }
}
