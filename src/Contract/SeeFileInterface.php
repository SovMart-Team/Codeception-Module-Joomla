<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

interface SeeFileInterface
{
    /** @return list<\JoomlaCodeception\Dto\FileExpectation> */
    public function seeFiles(FixtureProviderInterface $fixtureProvider): array;
}
