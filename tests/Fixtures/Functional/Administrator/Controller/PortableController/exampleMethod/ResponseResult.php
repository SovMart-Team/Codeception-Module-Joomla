<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Fixtures\Functional\Administrator\Controller\PortableController\exampleMethod;

use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Contract\ResponseResultInterface;
use JoomlaCodeception\Dto\ResponseExpectation;

final readonly class ResponseResult implements ResponseResultInterface
{
    public function response(FixtureProviderInterface $fixtureProvider): ResponseExpectation
    {
        return new ResponseExpectation();
    }
}
