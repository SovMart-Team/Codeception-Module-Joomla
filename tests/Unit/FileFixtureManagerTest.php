<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use JoomlaCodeception\Dto\FixtureFile;
use JoomlaCodeception\Service\CleanupJournal;
use JoomlaCodeception\Service\FileFixtureManager;
use JoomlaCodeception\Service\FixtureProvider;
use PHPUnit\Framework\TestCase;

final class FileFixtureManagerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/joomla-file-manager-test-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/images/component/existing', 0775, true);
        mkdir($this->root . '/storage', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);

    }

    public function testRestoresModifiedDeletedFilesPermissionsAndDirectories(): void
    {
        $existing = $this->root . '/images/component/existing/original.txt';
        $deleted  = $this->root . '/storage/deleted.txt';
        file_put_contents($existing, 'original');
        file_put_contents($deleted, 'restore me');
        chmod($existing, 0640);
        $manager  = $this->manager();
        $baseline = $manager->captureBaseline();

        file_put_contents($existing, 'changed');
        chmod($existing, 0666);
        unlink($deleted);
        mkdir($this->root . '/images/component/created/nested', 0775, true);
        file_put_contents($this->root . '/images/component/created/nested/new.txt', 'new');

        $manager->restoreBaseline($baseline);

        self::assertSame('original', file_get_contents($existing));
        self::assertSame('restore me', file_get_contents($deleted));
        self::assertSame(0640, fileperms($existing) & 0777);
        self::assertDirectoryDoesNotExist($this->root . '/images/component/created');
    }

    public function testPreservesBackupAfterFailedRestoreAndRemovesItAfterRetry(): void
    {
        $existing = $this->root . '/storage/original.txt';
        file_put_contents($existing, 'original');
        $manager       = $this->manager();
        $baseline      = $manager->captureBaseline();
        $backup        = $baseline->files[$existing]['backup'];
        $backupContent = file_get_contents($backup);
        self::assertIsString($backupContent);

        file_put_contents($existing, 'changed');
        unlink($backup);

        set_error_handler(static fn (): bool => true);

        try {
            $manager->restoreBaseline($baseline);
            self::fail('A missing backup file did not fail baseline restoration.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('backup preserved at ' . $baseline->backupRoot, $exception->getMessage());
            self::assertDirectoryExists($baseline->backupRoot);
        } finally {
            restore_error_handler();
        }

        file_put_contents($backup, $backupContent);
        $manager->restoreBaseline($baseline);

        self::assertSame('original', file_get_contents($existing));
        self::assertDirectoryDoesNotExist($baseline->backupRoot);
    }

    public function testRejectsPathThroughExistingSymlink(): void
    {
        $outside = $this->root . '/outside';
        mkdir($outside, 0775, true);

        if (!symlink($outside, $this->root . '/images/component/linked')) {
            self::markTestSkipped('Symlinks are unavailable in this environment.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('contains a symlink');
        $this->manager()->path('images', 'linked/escape.txt');
    }

    public function testRemovesRootThatWasAbsentFromBaseline(): void
    {
        $tmpRoot  = $this->root . '/tmp';
        $manager  = $this->manager();
        $baseline = $manager->captureBaseline();
        mkdir($tmpRoot, 0775, true);
        file_put_contents($tmpRoot . '/created.txt', 'created');

        $manager->restoreBaseline($baseline);

        self::assertDirectoryDoesNotExist($tmpRoot);
    }

    public function testMakesCreatedFixtureDirectoryWritableByTheApplicationUser(): void
    {
        file_put_contents($this->root . '/existing.zip', 'fixture');
        $manager = $this->manager();

        $manager->materialize([
            new FixtureFile('existing.zip', 'storage', 'method-owned/existing.zip'),
        ], new FixtureProvider($this->root));

        self::assertSame(0777, fileperms($this->root . '/storage/method-owned') & 0777);
        self::assertSame(0666, fileperms($this->root . '/storage/method-owned/existing.zip') & 0777);
    }

    public function testResolvesPublicImagePathWithOrWithoutLeadingSlash(): void
    {
        $manager  = $this->manager();
        $expected = $this->root . '/images/component/example.jpg';

        self::assertSame($expected, $manager->publicImagePath('/images/component/example.jpg'));
        self::assertSame($expected, $manager->publicImagePath('images/component/example.jpg'));
    }

    private function manager(): FileFixtureManager
    {
        return new FileFixtureManager([
            'images'  => $this->root . '/images/component',
            'storage' => $this->root . '/storage',
            'tmp'     => $this->root . '/tmp',
        ], '/images/component', new CleanupJournal());
    }

    private function removeTree(string $directory): void
    {
        if (!file_exists($directory) && !is_link($directory)) {
            return;
        }

        if (is_link($directory) || is_file($directory)) {
            unlink($directory);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $entry) {
            if ($entry->isLink() || $entry->isFile()) {
                unlink($entry->getPathname());
            } else {
                rmdir($entry->getPathname());
            }
        }

        rmdir($directory);
    }
}
