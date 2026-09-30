<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

use JoomlaCodeception\Context\FixtureCapabilityLoader;
use JoomlaCodeception\Context\FixtureContractValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExampleFixturesTest extends TestCase
{
    #[DataProvider('fixtureDirectories')]
    public function testExampleFixtureContractIsValid(string $relativeDirectory): void
    {
        $fixtureRoot = dirname(__DIR__, 2) . '/examples/Fixtures/Functional';
        $directory   = $fixtureRoot . '/' . $relativeDirectory;
        $capabilities = (new FixtureCapabilityLoader(
            $fixtureRoot,
            'Example\\Tests\\Fixtures\\Functional',
        ))->load($directory);

        (new FixtureContractValidator())->validate($capabilities, $directory);
        self::addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function fixtureDirectories(): iterable
    {
        yield 'API status' => ['Api/Controller/Status/returnsVersion'];
        yield 'Administrator article save' => ['Administrator/Controller/Article/saveSucceeds'];
    }
}
