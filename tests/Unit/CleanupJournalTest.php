<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use JoomlaCodeception\Service\CleanupJournal;
use PHPUnit\Framework\TestCase;

final class CleanupJournalTest extends TestCase
{
    public function testCleansResourcesInReverseOrder(): void
    {
        $calls   = [];
        $journal = new CleanupJournal();
        $journal->register('first', static function () use (&$calls): void {
            $calls[] = 'first';
        });
        $journal->register('second', static function () use (&$calls): void {
            $calls[] = 'second';
        });

        $journal->cleanup();

        self::assertSame(['second', 'first'], $calls);
        self::assertTrue($journal->isEmpty());
    }

    public function testKeepsEarlierGuardsUntilFailedCleanupCanBeRetried(): void
    {
        $calls            = [];
        $baselineAttempts = 0;
        $journal = new CleanupJournal();
        $journal->register('advisory lock', static function () use (&$calls): void {
            $calls[] = 'lock';
        });
        $journal->register('baseline', static function () use (&$calls, &$baselineAttempts): void {
            $calls[] = 'baseline';
            ++$baselineAttempts;

            if ($baselineAttempts === 1) {
                throw new \RuntimeException('restore failed');
            }
        });
        $journal->register('fixture', static function () use (&$calls): void {
            $calls[] = 'fixture';
        });

        try {
            $journal->cleanup();
            self::fail('Cleanup failure was not reported.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('baseline: restore failed', $exception->getMessage());
            self::assertSame(['fixture', 'baseline'], $calls);
            self::assertFalse($journal->isEmpty());
        }

        $journal->cleanup();

        self::assertSame(['fixture', 'baseline', 'baseline', 'lock'], $calls);
        self::assertTrue($journal->isEmpty());
    }
}
