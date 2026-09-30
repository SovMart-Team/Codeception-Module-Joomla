<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class FileCountExpectation
{
    public function __construct(public string $root, public string $relativePath, public int $count)
    {
    }
}
