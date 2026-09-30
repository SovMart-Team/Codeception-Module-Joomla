<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class FixtureReference
{
    public function __construct(
        public string $fixtureClass,
        public string $key,
        public string $column = 'id',
    ) {
    }
}
