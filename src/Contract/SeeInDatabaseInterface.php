<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

interface SeeInDatabaseInterface
{
    /** @return list<\JoomlaCodeception\Dto\DatabaseCriteria> */
    public function seeInDatabase(FixtureProviderInterface $fixtureProvider): array;
}
