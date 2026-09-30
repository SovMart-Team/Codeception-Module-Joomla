<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

interface ConfigurationFixtureApplierInterface
{
    /** @param array<string, mixed> $values */
    public function apply(array $values): void;
}
