<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Fixtures\Functional\Administrator\Controller\PortableController\exampleMethod;

use JoomlaCodeception\Contract\FixtureInterface;
use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Dto\DatabaseFixture as DatabaseFixtureData;

final readonly class DatabaseFixture implements FixtureInterface
{
    public function getFixturesData(FixtureProviderInterface $fixtureProvider): array
    {
        return [
            new DatabaseFixtureData(
                key: 'record',
                table: '#__portable_records',
                values: ['name' => 'Portable fixture'],
            ),
        ];
    }
}
