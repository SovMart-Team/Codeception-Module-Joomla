<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

use JoomlaCodeception\Dto\ResponseExpectation;

interface ResponseResultInterface
{
    public function response(FixtureProviderInterface $fixtureProvider): ResponseExpectation;
}
