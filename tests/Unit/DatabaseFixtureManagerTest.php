<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use JoomlaCodeception\Contract\FixtureInterface;
use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Dto\DatabaseFixture;
use JoomlaCodeception\Dto\MutationMode;
use JoomlaCodeception\Service\CleanupJournal;
use JoomlaCodeception\Service\DatabaseFixtureManager;
use JoomlaCodeception\Service\FixtureProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseFixtureManagerTest extends TestCase
{
    public function testResolvesCriteriaReferencesBeforeApplyingFixture(): void
    {
        $database = $this->database();
        $database->exec("INSERT INTO jos_records (linked_id, slug) VALUES (1, 'before')");
        $journal    = new CleanupJournal();
        $manager    = new DatabaseFixtureManager($database, 'jos_', $journal);
        $provider   = new FixtureProvider(__DIR__);
        $capability = new ReferencedCriteriaDatabaseFixture();

        $manager->apply([$capability], $provider);

        self::assertSame('after', $database->query('SELECT slug FROM jos_records')->fetchColumn());
        $journal->cleanup();
        self::assertSame('before', $database->query('SELECT slug FROM jos_records')->fetchColumn());
        self::assertSame(0, (int) $database->query('SELECT COUNT(*) FROM jos_dependencies')->fetchColumn());
    }

    public function testRestoresRecordWhenMutationChangesCriterionColumn(): void
    {
        $database = $this->database();
        $database->exec("INSERT INTO jos_records (linked_id, slug) VALUES (7, 'old')");
        $journal = new CleanupJournal();
        $manager = new DatabaseFixtureManager($database, 'jos_', $journal);

        $manager->apply([new ChangedCriterionDatabaseFixture()], new FixtureProvider(__DIR__));
        self::assertSame('new', $database->query('SELECT slug FROM jos_records')->fetchColumn());

        $journal->cleanup();
        self::assertSame('old', $database->query('SELECT slug FROM jos_records')->fetchColumn());
    }

    public function testRemovesCreatedRowsInReverseTableOrderBeforeFixtureCleanup(): void
    {
        $database = $this->database();
        $database->exec('PRAGMA foreign_keys = ON');
        $journal  = new CleanupJournal();
        $manager  = new DatabaseFixtureManager($database, 'jos_', $journal);
        $provider = new FixtureProvider(__DIR__);

        $manager->apply([new ParentDatabaseFixture()], $provider);
        $baseline = $manager->captureBaseline(['#__dependencies', '#__records']);
        $journal->register('database request baseline', fn () => $manager->removeCreatedRows($baseline));
        $database->exec("INSERT INTO jos_records (linked_id, slug) VALUES (1, 'created-by-request')");

        $journal->cleanup();

        self::assertSame(0, (int) $database->query('SELECT COUNT(*) FROM jos_records')->fetchColumn());
        self::assertSame(0, (int) $database->query('SELECT COUNT(*) FROM jos_dependencies')->fetchColumn());
    }

    public function testKeepsBaselineRowWhenRequestChangesNonKeyValues(): void
    {
        $database = $this->database();
        $database->exec("INSERT INTO jos_records (linked_id, slug) VALUES (1, 'before')");
        $manager  = new DatabaseFixtureManager($database, 'jos_', new CleanupJournal());
        $baseline = $manager->captureBaseline(['#__records']);

        $database->exec("UPDATE jos_records SET slug = 'after' WHERE id = 1");
        $manager->removeCreatedRows($baseline);

        self::assertSame('after', $database->query('SELECT slug FROM jos_records WHERE id = 1')->fetchColumn());
    }

    private function database(): \PDO
    {
        $database = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE jos_dependencies (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT NOT NULL)');
        $database->exec('CREATE TABLE jos_records (id INTEGER PRIMARY KEY AUTOINCREMENT, linked_id INTEGER NOT NULL, slug TEXT NOT NULL, FOREIGN KEY (linked_id) REFERENCES jos_dependencies(id))');

        return $database;
    }
}

final readonly class ParentDatabaseFixture implements FixtureInterface
{
    public function getFixturesData(FixtureProviderInterface $fixtureProvider): array
    {
        return [
            new DatabaseFixture('parent', '#__dependencies', ['label' => 'parent']),
        ];
    }
}

final readonly class ReferencedCriteriaDatabaseFixture implements FixtureInterface
{
    public function getFixturesData(FixtureProviderInterface $fixtureProvider): array
    {
        return [
            new DatabaseFixture(
                key: 'target',
                table: '#__records',
                values: ['slug' => 'after'],
                mode: MutationMode::Update,
                criteria: ['linked_id' => $fixtureProvider->reference(self::class, 'dependency')],
            ),
            new DatabaseFixture('dependency', '#__dependencies', ['label' => 'dependency']),
        ];
    }
}

final readonly class ChangedCriterionDatabaseFixture implements FixtureInterface
{
    public function getFixturesData(FixtureProviderInterface $fixtureProvider): array
    {
        return [
            new DatabaseFixture(
                key: 'target',
                table: '#__records',
                values: ['slug' => 'new'],
                mode: MutationMode::Restore,
                criteria: ['slug' => 'old'],
            ),
        ];
    }
}
