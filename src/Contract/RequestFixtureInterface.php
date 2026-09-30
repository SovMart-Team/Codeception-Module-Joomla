<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

use JoomlaCodeception\Dto\RequestData;

interface RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData;
}
