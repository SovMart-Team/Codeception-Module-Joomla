<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Fixtures\Functional\Administrator\Controller\PortableController\exampleMethod;

use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Contract\RequestFixtureInterface;
use JoomlaCodeception\Dto\RequestData;

final readonly class RequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(fields: [
            'record_id' => $fixtureProvider->reference(DatabaseFixture::class, 'record'),
        ]);
    }
}
