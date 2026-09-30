<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Contract\FixtureInterface;
use JoomlaCodeception\Dto\DatabaseFixture;
use JoomlaCodeception\Dto\FixtureReference;
use JoomlaCodeception\Dto\MutationMode;

final readonly class DatabaseFixtureManager
{
    public function __construct(
        private \PDO $database,
        private string $prefix,
        private CleanupJournal $cleanupJournal,
    ) {
    }

    /** @param list<object> $capabilities */
    public function apply(array $capabilities, FixtureProvider $provider): void
    {
        $pending = [];
        $keys    = [];

        foreach ($capabilities as $capability) {
            if (!$capability instanceof FixtureInterface) {
                continue;
            }

            foreach ($capability->getFixturesData($provider) as $fixture) {
                if (!$fixture instanceof DatabaseFixture) {
                    throw new \RuntimeException(sprintf('%s returned a non-DatabaseFixture value.', $capability::class));
                }

                $signature = $capability::class . '::' . $fixture->key;

                if (isset($keys[$signature])) {
                    throw new \RuntimeException(sprintf('Duplicate fixture record: %s', $signature));
                }

                if ($fixture->key === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $fixture->key)) {
                    throw new \RuntimeException(sprintf('Invalid fixture key: %s', $signature));
                }

                $this->table($fixture->table);
                $this->assertColumns(array_keys($fixture->values));
                $this->assertColumns(array_keys($fixture->criteria));

                if ($fixture->values === []) {
                    throw new \RuntimeException(sprintf('Fixture values cannot be empty: %s', $signature));
                }

                if ($fixture->mode !== MutationMode::Insert && $fixture->criteria === []) {
                    throw new \RuntimeException(sprintf(
                        'Fixture %s requires criteria for %s.',
                        $signature,
                        $fixture->mode->value,
                    ));
                }

                $keys[$signature] = true;
                $pending[]        = ['class' => $capability::class, 'fixture' => $fixture];
            }
        }

        while ($pending !== []) {
            $progress = false;

            foreach ($pending as $index => $entry) {
                /** @var DatabaseFixture $fixture */
                $fixture = $entry['fixture'];

                if (
                    !$this->referencesResolved($fixture->values, $provider)
                    || !$this->referencesResolved($fixture->criteria, $provider)
                ) {
                    continue;
                }

                $this->applyFixture($entry['class'], $fixture, $provider);
                unset($pending[$index]);
                $progress = true;
            }

            if (!$progress) {
                $chain = array_map(
                    static fn (array $entry): string => $entry['class'] . '::' . $entry['fixture']->key,
                    array_values($pending),
                );
                throw new \RuntimeException('Fixture dependency cycle or unknown reference: ' . implode(' -> ', $chain));
            }

            $pending = array_values($pending);
        }
    }

    public function table(string $name): string
    {
        if (!str_starts_with($name, '#__')) {
            throw new \RuntimeException(sprintf('Fixture table must use Joomla #__ prefix: %s', $name));
        }

        $suffix = substr($name, 3);

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $suffix)) {
            throw new \RuntimeException(sprintf('Invalid fixture table: %s', $name));
        }

        return $this->prefix . $suffix;
    }

    /** @param list<string> $tables @return array<string, int|string|null> */
    public function snapshot(array $tables): array
    {
        $snapshot = [];

        foreach ($tables as $table) {
            $result           = $this->database->query(sprintf('CHECKSUM TABLE `%s`', $this->table($table)))->fetch(\PDO::FETCH_ASSOC);
            $snapshot[$table] = is_array($result) ? ($result['Checksum'] ?? null) : null;
        }

        ksort($snapshot);

        return $snapshot;
    }

    /**
     * @param list<string> $tables
     * @return array<string, array{primaryKey: list<string>, rows: list<array<string, mixed>>}>
     */
    public function captureBaseline(array $tables): array
    {
        $baseline = [];

        foreach (array_values(array_unique($tables)) as $table) {
            $physicalTable    = $this->table($table);
            $rows             = $this->database->query(sprintf('SELECT * FROM `%s`', $physicalTable))->fetchAll(\PDO::FETCH_ASSOC);
            $baseline[$table] = [
                'primaryKey' => $this->primaryKeyColumns($physicalTable),
                'rows'       => array_values(array_filter($rows, 'is_array')),
            ];
        }

        return $baseline;
    }

    /**
     * Delete rows created after a baseline was captured. Tables are processed in
     * reverse declaration order so child records are removed before parents.
     *
     * @param array<string, array{primaryKey: list<string>, rows: list<array<string, mixed>>}> $baseline
     */
    public function removeCreatedRows(array $baseline): void
    {
        foreach (array_reverse($baseline, true) as $table => $state) {
            $physicalTable = $this->table($table);
            $rows          = $this->database->query(sprintf('SELECT * FROM `%s`', $physicalTable))->fetchAll(\PDO::FETCH_ASSOC);
            $knownRows     = [];

            foreach ($state['rows'] as $row) {
                $identity             = $this->rowIdentity($row, $state['primaryKey']);
                $knownRows[$identity] = ($knownRows[$identity] ?? 0) + 1;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $identity = $this->rowIdentity($row, $state['primaryKey']);

                if (($knownRows[$identity] ?? 0) > 0) {
                    --$knownRows[$identity];

                    continue;
                }

                $criteria = $state['primaryKey'] === []
                    ? $row
                    : array_intersect_key($row, array_fill_keys($state['primaryKey'], true));
                $this->delete($physicalTable, $criteria);
            }
        }
    }

    private function applyFixture(string $fixtureClass, DatabaseFixture $fixture, FixtureProvider $provider): void
    {
        $values   = $provider->resolve($fixture->values);
        $criteria = $provider->resolve($fixture->criteria);
        $table    = $this->table($fixture->table);

        if (!is_array($values) || !is_array($criteria)) {
            throw new \RuntimeException(sprintf('Fixture %s::%s did not resolve to arrays.', $fixtureClass, $fixture->key));
        }

        $this->assertColumns(array_keys($values));
        $this->assertColumns(array_keys($criteria));

        if ($fixture->mode === MutationMode::Insert) {
            $record          = $this->insert($table, $values);
            $cleanupCriteria = isset($record['id']) && (int) $record['id'] > 0 ? ['id' => $record['id']] : $values;
            $this->cleanupJournal->register(
                sprintf('database %s::%s', $fixtureClass, $fixture->key),
                fn () => $this->delete($table, $cleanupCriteria),
            );
            $provider->register($fixtureClass, $fixture->key, $record);

            return;
        }

        if ($criteria === []) {
            throw new \RuntimeException(sprintf('Fixture %s::%s requires criteria for %s.', $fixtureClass, $fixture->key, $fixture->mode->value));
        }

        $before = $this->findOne($table, $criteria);

        if ($before === null) {
            throw new \RuntimeException(sprintf('Fixture target is missing: %s::%s', $fixtureClass, $fixture->key));
        }

        $after           = array_replace($before, $values);
        $restoreCriteria = isset($after['id'])
            ? ['id' => $after['id']]
            : array_replace($criteria, array_intersect_key($after, $criteria));
        $this->update($table, $values, $criteria);
        $this->cleanupJournal->register(
            sprintf('database restore %s::%s', $fixtureClass, $fixture->key),
            fn () => $this->update($table, $before, $restoreCriteria),
        );
        $provider->register($fixtureClass, $fixture->key, $after);
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function insert(string $table, array $values): array
    {
        if ($values === []) {
            throw new \RuntimeException(sprintf('Refusing empty fixture insert into %s.', $table));
        }

        $columns   = array_keys($values);
        $statement = $this->database->prepare(sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (:%s)',
            $table,
            implode('`, `', $columns),
            implode(', :', $columns),
        ));
        $statement->execute($values);
        $record = $values;
        $id     = (int) $this->database->lastInsertId();

        if ($id > 0) {
            $record['id'] = $id;
        }

        return $record;
    }

    /** @param array<string, mixed> $values @param array<string, mixed> $criteria */
    private function update(string $table, array $values, array $criteria): void
    {
        if ($values === [] || $criteria === []) {
            throw new \RuntimeException('Refusing unbounded or empty fixture update.');
        }

        $sets                 = array_map(static fn (string $column): string => sprintf('`%s` = :set_%s', $column, $column), array_keys($values));
        [$where, $parameters] = $this->where($criteria, 'where_');
        $statement            = $this->database->prepare(sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where));
        $statement->execute(array_merge(
            array_combine(array_map(static fn (string $column): string => 'set_' . $column, array_keys($values)), array_values($values)),
            $parameters,
        ));
    }

    /** @param array<string, mixed> $criteria */
    private function delete(string $table, array $criteria): void
    {
        if ($criteria === []) {
            throw new \RuntimeException('Refusing fixture cleanup without criteria.');
        }

        [$where, $parameters] = $this->where($criteria);
        $statement            = $this->database->prepare(sprintf('DELETE FROM `%s` WHERE %s', $table, $where));
        $statement->execute($parameters);
    }

    /** @param array<string, mixed> $criteria @return array<string, mixed>|null */
    private function findOne(string $table, array $criteria): ?array
    {
        [$where, $parameters] = $this->where($criteria);
        $statement            = $this->database->prepare(sprintf('SELECT * FROM `%s` WHERE %s LIMIT 2', $table, $where));
        $statement->execute($parameters);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) > 1) {
            throw new \RuntimeException(sprintf('Fixture criteria matched multiple records in %s.', $table));
        }

        return isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @param array<string, mixed> $criteria @return array{string, array<string, mixed>} */
    private function where(array $criteria, string $parameterPrefix = ''): array
    {
        $this->assertColumns(array_keys($criteria));
        $parts      = [];
        $parameters = [];

        foreach ($criteria as $column => $value) {
            if ($value === null) {
                $parts[] = sprintf('`%s` IS NULL', $column);

                continue;
            }

            $parameter              = $parameterPrefix . $column;
            $parts[]                = sprintf('`%s` = :%s', $column, $parameter);
            $parameters[$parameter] = $value;
        }

        return [implode(' AND ', $parts), $parameters];
    }

    /** @param list<string> $columns */
    private function assertColumns(array $columns): void
    {
        foreach ($columns as $column) {
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $column)) {
                throw new \RuntimeException(sprintf('Invalid fixture column: %s', $column));
            }
        }
    }

    private function referencesResolved(mixed $value, FixtureProvider $provider): bool
    {
        if ($value instanceof FixtureReference) {
            return $provider->hasReference($value);
        }

        if (!is_array($value)) {
            return true;
        }

        foreach ($value as $item) {
            if (!$this->referencesResolved($item, $provider)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function primaryKeyColumns(string $table): array
    {
        $driver = (string) $this->database->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $rows    = $this->database->query(sprintf('PRAGMA table_info(`%s`)', $table))->fetchAll(\PDO::FETCH_ASSOC);
            $primary = [];

            foreach ($rows as $row) {
                if (!is_array($row) || (int) ($row['pk'] ?? 0) < 1) {
                    continue;
                }

                $primary[(int) $row['pk']] = (string) $row['name'];
            }

            ksort($primary);

            return array_values($primary);
        }

        $rows    = $this->database->query(sprintf("SHOW KEYS FROM `%s` WHERE Key_name = 'PRIMARY'", $table))->fetchAll(\PDO::FETCH_ASSOC);
        $primary = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $primary[(int) ($row['Seq_in_index'] ?? 0)] = (string) ($row['Column_name'] ?? '');
        }

        ksort($primary);

        return array_values(array_filter($primary, static fn (string $column): bool => $column !== ''));
    }

    /** @param array<string, mixed> $row @param list<string> $primaryKey */
    private function rowIdentity(array $row, array $primaryKey): string
    {
        if ($primaryKey === []) {
            return hash('sha256', serialize($row));
        }

        $identity = [];

        foreach ($primaryKey as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \RuntimeException(sprintf('Primary key column %s is missing from a database baseline row.', $column));
            }

            $identity[$column] = $row[$column];
        }

        return hash('sha256', serialize($identity));
    }
}
