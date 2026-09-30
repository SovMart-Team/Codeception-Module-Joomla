<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class SideEffectSnapshot
{
    /** @param array<string, int|string|null> $database @param array<string, string> $filesystem */
    public function __construct(public array $database, public array $filesystem)
    {
    }
}
