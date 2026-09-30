<?php

declare(strict_types=1);

namespace Example\Tests\Fixtures\Functional\Api\Controller\Status\returnsVersion;

use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Contract\RequestFixtureInterface;
use JoomlaCodeception\Dto\CsrfMode;
use JoomlaCodeception\Dto\RequestData;

final readonly class RequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(csrfMode: CsrfMode::Missing);
    }
}
