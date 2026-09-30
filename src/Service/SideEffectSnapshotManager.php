<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Dto\SideEffectSnapshot;
use PHPUnit\Framework\Assert;

final readonly class SideEffectSnapshotManager
{
    public function __construct(
        private DatabaseFixtureManager $database,
        private FileFixtureManager $filesystem,
    ) {
    }

    /** @param list<string> $tables */
    public function capture(array $tables): SideEffectSnapshot
    {
        return new SideEffectSnapshot($this->database->snapshot($tables), $this->filesystem->snapshot());
    }

    /** @param list<string> $tables */
    public function assertUnchanged(SideEffectSnapshot $before, array $tables): void
    {
        Assert::assertSame($before->database, $this->database->snapshot($tables));
        Assert::assertSame($before->filesystem, $this->filesystem->snapshot());
    }
}
