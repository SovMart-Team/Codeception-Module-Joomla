<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use JoomlaCodeception\Context\FixtureCapabilityLoader;
use JoomlaCodeception\Contract\RequestFixtureInterface;
use JoomlaCodeception\Contract\ResponseResultInterface;
use PHPUnit\Framework\TestCase;

final class FixtureCapabilityLoaderTest extends TestCase
{
    public function testLoadsFixtureAndResultClassesByNamingConvention(): void
    {
        $fixtureRoot  = dirname(__DIR__) . '/Fixtures/Functional';
        $directory    = $fixtureRoot . '/Administrator/Controller/PortableController/exampleMethod';
        $capabilities = (new FixtureCapabilityLoader(
            $fixtureRoot,
            'JoomlaCodeception\\Tests\\Fixtures\\Functional',
        ))->load($directory);

        self::assertNotEmpty(array_filter($capabilities, static fn (object $item): bool => $item instanceof RequestFixtureInterface));
        self::assertNotEmpty(array_filter($capabilities, static fn (object $item): bool => $item instanceof ResponseResultInterface));
    }
}
