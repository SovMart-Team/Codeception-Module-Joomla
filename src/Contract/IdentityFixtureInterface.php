<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

interface IdentityFixtureInterface
{
    /** @return array<string, int> */
    public function rootPermissions(): array;

    /** @return array<string, int> */
    public function componentPermissions(): array;
}
