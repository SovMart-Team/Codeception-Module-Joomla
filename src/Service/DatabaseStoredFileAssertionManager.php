<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Contract\DatabaseStoredFileResultInterface;
use JoomlaCodeception\Dto\DatabaseStoredFileExpectation;
use PHPUnit\Framework\Assert;

final readonly class DatabaseStoredFileAssertionManager
{
    public function __construct(
        private \PDO $database,
        private DatabaseFixtureManager $fixtures,
        private DatabaseConditionBuilder $conditions,
        private FileFixtureManager $files,
    ) {
    }

    /** @param list<object> $capabilities */
    public function assert(array $capabilities, FixtureProvider $provider): void
    {
        foreach ($capabilities as $capability) {
            if (!$capability instanceof DatabaseStoredFileResultInterface) {
                continue;
            }

            foreach ($capability->databaseStoredFiles($provider) as $expectation) {
                $this->assertStoredFile($expectation, $provider);
            }
        }
    }

    private function assertStoredFile(DatabaseStoredFileExpectation $expectation, FixtureProvider $provider): void
    {
        $criteria = $provider->resolve($expectation->criteria);
        Assert::assertIsArray($criteria);
        $condition = $this->conditions->build($criteria);

        if ($condition['semantic'] !== []) {
            throw new \RuntimeException('Semantic JSON conditions are unsupported for database stored-file criteria.');
        }

        $statement = $this->database->prepare(sprintf(
            'SELECT `%s` FROM `%s` WHERE %s',
            $expectation->field,
            $this->fixtures->table($expectation->table),
            $condition['sql'],
        ));
        $statement->execute($condition['parameters']);
        $values = $statement->fetchAll(\PDO::FETCH_COLUMN);
        Assert::assertCount(1, $values, sprintf(
            'Expected exactly one row in %s for stored-file assertion.',
            $expectation->table,
        ));

        $rawValue = $values[0];
        Assert::assertIsString($rawValue, sprintf(
            'Stored-file field %s.%s must contain a string.',
            $expectation->table,
            $expectation->field,
        ));
        $decoded    = null;
        $storedPath = $rawValue;

        if ($expectation->jsonPath !== null || $expectation->jsonEquals !== []) {
            $decoded = json_decode($rawValue, true, 512, JSON_THROW_ON_ERROR);
            Assert::assertIsArray($decoded, sprintf(
                'Stored-file field %s.%s must contain a JSON object or array.',
                $expectation->table,
                $expectation->field,
            ));

            foreach ($expectation->jsonEquals as $path => $expected) {
                Assert::assertSame($expected, $this->jsonValue($decoded, $path));
            }

            if ($expectation->jsonPath !== null) {
                $storedPath = $this->jsonValue($decoded, $expectation->jsonPath);
                Assert::assertIsString($storedPath, sprintf(
                    'Stored-file JSON path %s must contain a string.',
                    $expectation->jsonPath,
                ));
            }
        }

        if ($expectation->valueEquals !== null) {
            Assert::assertSame($expectation->valueEquals, $storedPath);
        }

        if ($expectation->valuePrefix !== null) {
            Assert::assertStringStartsWith($expectation->valuePrefix, $storedPath);
        }

        if ($expectation->valueSuffix !== null) {
            Assert::assertStringEndsWith($expectation->valueSuffix, $storedPath);
        }

        if ($expectation->fileExists === null) {
            return;
        }

        if ($expectation->root === null) {
            throw new \RuntimeException('Database stored-file root is required when file existence is asserted.');
        }

        $path = $expectation->root === 'public-images'
            ? $this->files->publicImagePath($storedPath)
            : $this->files->path($expectation->root, $storedPath);

        if ($expectation->fileExists) {
            Assert::assertFileExists($path);
        } else {
            Assert::assertFileDoesNotExist($path);
        }
    }

    /** @param array<mixed> $decoded */
    private function jsonValue(array $decoded, string $path): mixed
    {
        $value = $decoded;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw new \RuntimeException(sprintf('Stored-file JSON path does not exist: %s', $path));
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
