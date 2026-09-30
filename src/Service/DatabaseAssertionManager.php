<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Contract\CountRecordsInterface;
use JoomlaCodeception\Contract\DontSeeInDatabaseInterface;
use JoomlaCodeception\Contract\SeeInDatabaseInterface;
use JoomlaCodeception\Dto\DatabaseCondition;
use JoomlaCodeception\Dto\DatabaseCountExpectation;
use JoomlaCodeception\Dto\DatabaseCriteria;
use JoomlaCodeception\Dto\DatabaseOperator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;

final readonly class DatabaseAssertionManager
{
    public function __construct(
        private \PDO $database,
        private DatabaseFixtureManager $fixtures,
        private DatabaseConditionBuilder $conditions,
    ) {
    }

    /** @param list<object> $capabilities */
    public function assert(array $capabilities, FixtureProvider $provider): void
    {
        foreach ($capabilities as $capability) {
            if ($capability instanceof SeeInDatabaseInterface) {
                foreach ($capability->seeInDatabase($provider) as $expectation) {
                    $this->assertCriteria($expectation, $provider, true);
                }
            }

            if ($capability instanceof DontSeeInDatabaseInterface) {
                foreach ($capability->dontSeeInDatabase($provider) as $expectation) {
                    $this->assertCriteria($expectation, $provider, false);
                }
            }

            if ($capability instanceof CountRecordsInterface) {
                foreach ($capability->countRecords($provider) as $expectation) {
                    $this->assertCount($expectation, $provider);
                }
            }
        }
    }

    private function assertCriteria(DatabaseCriteria $expectation, FixtureProvider $provider, bool $present): void
    {
        $criteria = $provider->resolve($expectation->criteria);
        Assert::assertIsArray($criteria);

        $this->eventually($expectation->timeoutMilliseconds, function () use ($expectation, $criteria, $present): void {
            $rows = $this->matchingRows($expectation->table, $criteria);

            if ($present) {
                Assert::assertNotEmpty($rows, sprintf('No matching row found in %s.', $expectation->table));
            } else {
                Assert::assertSame([], $rows, sprintf('Unexpected matching row found in %s.', $expectation->table));
            }
        });
    }

    private function assertCount(DatabaseCountExpectation $expectation, FixtureProvider $provider): void
    {
        $criteria = $provider->resolve($expectation->criteria);
        Assert::assertIsArray($criteria);

        if ($criteria === []) {
            throw new \RuntimeException(sprintf('Count expectation for %s requires bounded criteria.', $expectation->table));
        }

        $this->eventually($expectation->timeoutMilliseconds, function () use ($expectation, $criteria): void {
            Assert::assertCount($expectation->count, $this->matchingRows($expectation->table, $criteria));
        });
    }

    /** @param array<string, mixed> $criteria @return list<array<string, mixed>> */
    private function matchingRows(string $table, array $criteria): array
    {
        $condition = $this->conditions->build($criteria);
        $statement = $this->database->prepare(sprintf(
            'SELECT * FROM `%s` WHERE %s',
            $this->fixtures->table($table),
            $condition['sql'],
        ));
        $statement->execute($condition['parameters']);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, fn (array $row): bool => $this->matchesSemanticJson($row, $condition['semantic'])));
    }

    /** @param array<string, mixed> $row @param array<string, DatabaseCondition> $semantic */
    private function matchesSemanticJson(array $row, array $semantic): bool
    {
        foreach ($semantic as $column => $condition) {
            try {
                $actual   = json_decode((string) ($row[$column] ?? ''), true, 512, JSON_THROW_ON_ERROR);
                $expected = is_string($condition->value)
                    ? json_decode($condition->value, true, 512, JSON_THROW_ON_ERROR)
                    : $condition->value;
            } catch (\JsonException) {
                return false;
            }

            $equal = $this->normalizeJson($actual) === $this->normalizeJson($expected);

            if (($condition->operator === DatabaseOperator::Equal && !$equal)
                || ($condition->operator === DatabaseOperator::NotEqual && $equal)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeJson(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeJson($item);
        }

        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function eventually(int $timeoutMilliseconds, callable $assertion): void
    {
        if ($timeoutMilliseconds < 0) {
            throw new \RuntimeException('Database assertion timeout cannot be negative.');
        }

        $deadline = microtime(true) + ($timeoutMilliseconds / 1000);

        while (true) {
            try {
                $assertion();

                return;
            } catch (AssertionFailedError $failure) {
                if (microtime(true) >= $deadline) {
                    throw $failure;
                }

                usleep(50_000);
            }
        }
    }
}
