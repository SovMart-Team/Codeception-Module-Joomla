<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

interface CountFilesInterface
{
    /** @return list<\JoomlaCodeception\Dto\FileCountExpectation> */
    public function countFiles(FixtureProviderInterface $fixtureProvider): array;
}
