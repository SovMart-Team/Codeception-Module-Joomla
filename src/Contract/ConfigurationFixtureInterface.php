<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

interface ConfigurationFixtureInterface
{
    /** @return array<string, mixed> */
    public function configuration(): array;
}
