<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

use JoomlaCodeception\Dto\DatabaseFixture;

interface FixtureInterface
{
    /** @return list<DatabaseFixture> */
    public function getFixturesData(FixtureProviderInterface $fixtureProvider): array;
}
