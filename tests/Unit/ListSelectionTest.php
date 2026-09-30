<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use JoomlaCodeception\Dto\ListSelection;
use JoomlaCodeception\Service\FixtureProvider;
use PHPUnit\Framework\TestCase;

final class ListSelectionTest extends TestCase
{
    public function testBuildsJoomlaListFieldsAndDerivesBoxchecked(): void
    {
        $selection = new ListSelection([10, 20]);

        self::assertSame(2, $selection->boxchecked);
        self::assertSame(
            ['cid' => [10, 20], 'boxchecked' => 2],
            $selection->fields([10, 20]),
        );
    }

    public function testPreservesStringIdentifiers(): void
    {
        $selection = new ListSelection(['first', 'second']);

        self::assertSame(
            ['cid' => ['first', 'second'], 'boxchecked' => 2],
            $selection->fields(['first', 'second']),
        );
    }

    public function testRejectsInvalidResolvedIdentifiers(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ListSelection([1]))->fields([0]);
    }

    public function testBuildsFieldsFromResolvedFixtureReferences(): void
    {
        $provider = new FixtureProvider(__DIR__);
        $provider->register(self::class, 'product', ['id' => 42]);
        $selection   = new ListSelection([$provider->reference(self::class, 'product')]);
        $resolvedIds = $provider->resolve($selection->ids);

        self::assertIsArray($resolvedIds);
        self::assertSame(['cid' => [42], 'boxchecked' => 1], $selection->fields($resolvedIds));
    }
}
