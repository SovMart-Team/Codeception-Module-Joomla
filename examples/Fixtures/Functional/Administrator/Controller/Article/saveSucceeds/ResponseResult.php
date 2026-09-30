<?php

declare(strict_types=1);

namespace Example\Tests\Fixtures\Functional\Administrator\Controller\Article\saveSucceeds;

use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Contract\ResponseResultInterface;
use JoomlaCodeception\Contract\SeeInDatabaseInterface;
use JoomlaCodeception\Dto\DatabaseCriteria;
use JoomlaCodeception\Dto\ResponseExpectation;

final readonly class ResponseResult implements ResponseResultInterface, SeeInDatabaseInterface
{
    public function response(FixtureProviderInterface $fixtureProvider): ResponseExpectation
    {
        return new ResponseExpectation(
            redirectContains: '/administrator/index.php',
            watchedTables: ['#__example_articles'],
        );
    }

    public function seeInDatabase(FixtureProviderInterface $fixtureProvider): array
    {
        return [
            new DatabaseCriteria(
                table: '#__example_articles',
                criteria: [
                    'title' => 'Created by Codeception',
                    'category_id' => $fixtureProvider->reference(DatabaseFixture::class, 'category'),
                ],
            ),
        ];
    }
}
