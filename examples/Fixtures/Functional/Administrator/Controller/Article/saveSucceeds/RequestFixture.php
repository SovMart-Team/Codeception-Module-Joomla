<?php

declare(strict_types=1);

namespace Example\Tests\Fixtures\Functional\Administrator\Controller\Article\saveSucceeds;

use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Contract\RequestFixtureInterface;
use JoomlaCodeception\Dto\CsrfMode;
use JoomlaCodeception\Dto\RequestData;

final readonly class RequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(
            fields: [
                'jform' => [
                    'title' => 'Created by Codeception',
                    'category_id' => $fixtureProvider->reference(DatabaseFixture::class, 'category'),
                    'published' => 1,
                ],
            ],
            csrfMode: CsrfMode::Form,
            task: 'article.save',
        );
    }
}
