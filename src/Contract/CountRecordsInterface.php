<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

interface CountRecordsInterface
{
    /** @return list<\JoomlaCodeception\Dto\DatabaseCountExpectation> */
    public function countRecords(FixtureProviderInterface $fixtureProvider): array;
}
