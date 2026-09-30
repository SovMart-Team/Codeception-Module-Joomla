<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use JoomlaCodeception\Context\FixtureDirectoryLocator;
use JoomlaCodeception\Dto\JoomlaClient;
use PHPUnit\Framework\TestCase;

final class FixtureDirectoryLocatorTest extends TestCase
{
    public function testResolvesClientLayerCestAndMethodDirectory(): void
    {
        $fixtureRoot = sys_get_temp_dir() . '/joomla-locator-' . bin2hex(random_bytes(6));
        $expected    = $fixtureRoot . '/Administrator/Unit/PortableController/exampleMethod';
        mkdir($expected, 0775, true);
        $directory = (new FixtureDirectoryLocator($fixtureRoot))->locate(
            JoomlaClient::Administrator,
            new PortableControllerCest(),
            'exampleMethod',
        );

        self::assertSame($expected, $directory);
        rmdir($expected);
        rmdir(dirname($expected));
        rmdir(dirname($expected, 2));
        rmdir(dirname($expected, 3));
        rmdir($fixtureRoot);
    }
}

final class PortableControllerCest
{
}
