<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use JoomlaCodeception\Contract\DatabaseStoredFileResultInterface;
use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Dto\DatabaseStoredFileExpectation;
use JoomlaCodeception\Service\CleanupJournal;
use JoomlaCodeception\Service\DatabaseConditionBuilder;
use JoomlaCodeception\Service\DatabaseFixtureManager;
use JoomlaCodeception\Service\DatabaseStoredFileAssertionManager;
use JoomlaCodeception\Service\FileFixtureManager;
use JoomlaCodeception\Service\FixtureProvider;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

final class DatabaseStoredFileAssertionManagerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/joomla-stored-file-' . bin2hex(random_bytes(8));

        foreach (['images/example', 'storage/releases', 'tmp'] as $directory) {
            mkdir($this->directory . '/' . $directory, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($this->directory);
    }

    public function testAssertsJsonStoredPublicImageAndCompanionValue(): void
    {
        $database = $this->database();
        $database->exec(<<<'SQL'
INSERT INTO jos_items (id, path, gallery)
VALUES (1, '', '{"row0":{"image":"/images/example/gallery.jpg","description":"Gallery image"}}')
SQL);
        file_put_contents($this->directory . '/images/example/gallery.jpg', 'image');
        $expectation = new DatabaseStoredFileExpectation(
            table: '#__items',
            field: 'gallery',
            criteria: ['id' => 1],
            root: 'public-images',
            jsonPath: 'row0.image',
            valuePrefix: '/images/example/',
            valueSuffix: '.jpg',
            fileExists: true,
            jsonEquals: ['row0.description' => 'Gallery image'],
        );

        $this->manager($database)->assert([new StoredFileResult([$expectation])], new FixtureProvider(__DIR__));
        self::addToAssertionCount(1);
    }

    public function testAssertsDirectStoragePathAndExactValue(): void
    {
        $database = $this->database();
        $database->exec("INSERT INTO jos_items (id, path, gallery) VALUES (1, 'releases/package.zip', '{}')");
        file_put_contents($this->directory . '/storage/releases/package.zip', 'archive');
        $expectation = new DatabaseStoredFileExpectation(
            table: '#__items',
            field: 'path',
            criteria: ['id' => 1],
            root: 'storage',
            valueEquals: 'releases/package.zip',
            valueSuffix: '.zip',
            fileExists: true,
        );

        $this->manager($database)->assert([new StoredFileResult([$expectation])], new FixtureProvider(__DIR__));
        self::addToAssertionCount(1);
    }

    public function testRejectsMissingStoredFile(): void
    {
        $database = $this->database();
        $database->exec("INSERT INTO jos_items (id, path, gallery) VALUES (1, 'releases/missing.zip', '{}')");
        $expectation = new DatabaseStoredFileExpectation(
            table: '#__items',
            field: 'path',
            criteria: ['id' => 1],
            root: 'storage',
            fileExists: true,
        );

        $this->expectException(AssertionFailedError::class);
        $this->manager($database)->assert([new StoredFileResult([$expectation])], new FixtureProvider(__DIR__));
    }

    private function database(): \PDO
    {
        $database = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE jos_items (id INTEGER PRIMARY KEY, path TEXT NOT NULL, gallery TEXT NOT NULL)');

        return $database;
    }

    private function manager(\PDO $database): DatabaseStoredFileAssertionManager
    {
        $journal = new CleanupJournal();

        return new DatabaseStoredFileAssertionManager(
            $database,
            new DatabaseFixtureManager($database, 'jos_', $journal),
            new DatabaseConditionBuilder(),
            new FileFixtureManager([
                'images'  => $this->directory . '/images',
                'storage' => $this->directory . '/storage',
                'tmp'     => $this->directory . '/tmp',
            ], '/images', $journal),
        );
    }
}

final readonly class StoredFileResult implements DatabaseStoredFileResultInterface
{
    /** @param list<DatabaseStoredFileExpectation> $expectations */
    public function __construct(private array $expectations)
    {
    }

    public function databaseStoredFiles(FixtureProviderInterface $fixtureProvider): array
    {
        return $this->expectations;
    }
}
