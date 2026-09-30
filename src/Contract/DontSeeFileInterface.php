<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

interface DontSeeFileInterface
{
    /** @return list<\JoomlaCodeception\Dto\FileExpectation> */
    public function dontSeeFiles(FixtureProviderInterface $fixtureProvider): array;
}
