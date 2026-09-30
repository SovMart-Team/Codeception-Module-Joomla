<?php

declare(strict_types=1);

namespace Example\Tests\Fixtures\Functional\Administrator\Controller\Article\saveSucceeds;

use JoomlaCodeception\Contract\FixtureInterface;
use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Dto\DatabaseFixture as DatabaseRow;

final readonly class DatabaseFixture implements FixtureInterface
{
    public function getFixturesData(FixtureProviderInterface $fixtureProvider): array
    {
        return [
            new DatabaseRow(
                key: 'category',
                table: '#__example_categories',
                values: [
                    'title' => 'Functional test category',
                    'published' => 1,
                ],
            ),
        ];
    }
}
