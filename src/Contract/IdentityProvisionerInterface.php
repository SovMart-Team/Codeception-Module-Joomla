<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

use JoomlaCodeception\Dto\JoomlaClient;

interface IdentityProvisionerInterface
{
    /** @return array<string, mixed> */
    public function create(JoomlaClient $client, IdentityFixtureInterface $fixture): array;
}
