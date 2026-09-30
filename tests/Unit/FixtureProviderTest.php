<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use JoomlaCodeception\Service\FixtureProvider;
use PHPUnit\Framework\TestCase;

final class FixtureProviderTest extends TestCase
{
    public function testResolvesNamedAssetRoot(): void
    {
        $provider = new FixtureProvider(__DIR__, ['security' => __DIR__]);

        self::assertSame(realpath(__FILE__), $provider->fixturePath('@security/FixtureProviderTest.php'));
    }

    public function testRejectsUnknownAssetRoot(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown fixture asset root');

        (new FixtureProvider(__DIR__))->fixturePath('@security/FixtureProviderTest.php');
    }

    public function testRejectsTraversalFromAssetRoot(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsafe fixture file path');

        (new FixtureProvider(__DIR__, ['security' => __DIR__]))->fixturePath('@security/../composer.json');
    }

    public function testRejectsAbsolutePath(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsafe fixture file path');

        (new FixtureProvider(__DIR__))->fixturePath(__FILE__);
    }
}
