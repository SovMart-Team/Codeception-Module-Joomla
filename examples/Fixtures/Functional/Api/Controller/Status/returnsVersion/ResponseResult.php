<?php

declare(strict_types=1);

namespace Example\Tests\Fixtures\Functional\Api\Controller\Status\returnsVersion;

use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Contract\ResponseResultInterface;
use JoomlaCodeception\Dto\ResponseExpectation;
use JoomlaCodeception\Dto\ResponseType;

final readonly class ResponseResult implements ResponseResultInterface
{
    public function response(FixtureProviderInterface $fixtureProvider): ResponseExpectation
    {
        return new ResponseExpectation(
            type: ResponseType::Json,
            noSideEffects: true,
            watchedTables: [
                '#__example_categories',
                '#__example_articles',
            ],
        );
    }
}
