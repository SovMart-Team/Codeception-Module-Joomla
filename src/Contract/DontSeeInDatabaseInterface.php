<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

interface DontSeeInDatabaseInterface
{
    /** @return list<\JoomlaCodeception\Dto\DatabaseCriteria> */
    public function dontSeeInDatabase(FixtureProviderInterface $fixtureProvider): array;
}
