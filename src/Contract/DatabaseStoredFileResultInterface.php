<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

interface DatabaseStoredFileResultInterface
{
    /** @return list<\JoomlaCodeception\Dto\DatabaseStoredFileExpectation> */
    public function databaseStoredFiles(FixtureProviderInterface $fixtureProvider): array;
}
