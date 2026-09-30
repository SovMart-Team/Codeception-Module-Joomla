<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Dto\DatabaseCondition;
use JoomlaCodeception\Dto\FixtureFile;
use JoomlaCodeception\Dto\FixtureReference;

final class FixtureProvider implements FixtureProviderInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $records = [];

    /** @param array<string, string> $assetRoots */
    public function __construct(
        private readonly string $fixtureDirectory,
        private readonly array $assetRoots = []
    ) {
    }

    public function reference(string $fixtureClass, string $key, string $column = 'id'): FixtureReference
    {
        return new FixtureReference($fixtureClass, $key, $column);
    }

    public function value(string $name): mixed
    {
        if (!array_key_exists($name, $this->values)) {
            throw new \RuntimeException(sprintf('Unknown fixture value: %s', $name));
        }

        return $this->values[$name];
    }

    public function file(string $relativePath): FixtureFile
    {
        return new FixtureFile($relativePath);
    }

    public function setValue(string $name, mixed $value): void
    {
        $this->values[$name] = $value;
    }

    /** @param array<string, mixed> $record */
    public function register(string $fixtureClass, string $key, array $record): void
    {
        if (isset($this->records[$fixtureClass][$key])) {
            throw new \RuntimeException(sprintf('Duplicate fixture record: %s::%s', $fixtureClass, $key));
        }

        $this->records[$fixtureClass][$key] = $record;
    }

    public function hasReference(FixtureReference $reference): bool
    {
        return array_key_exists($reference->column, $this->records[$reference->fixtureClass][$reference->key] ?? []);
    }

    public function resolve(mixed $value): mixed
    {
        if ($value instanceof FixtureReference) {
            if (!$this->hasReference($value)) {
                throw new \RuntimeException(sprintf(
                    'Unresolved fixture reference: %s::%s.%s',
                    $value->fixtureClass,
                    $value->key,
                    $value->column,
                ));
            }

            return $this->records[$value->fixtureClass][$value->key][$value->column];
        }

        if ($value instanceof DatabaseCondition) {
            return new DatabaseCondition(
                $value->operator,
                $this->resolve($value->value),
                $value->semanticJson,
            );
        }

        if (!is_array($value)) {
            return $value;
        }

        $resolved = [];

        foreach ($value as $key => $item) {
            $resolved[$key] = $this->resolve($item);
        }

        return $resolved;
    }

    public function fixturePath(string $relativePath): string
    {
        $root = $this->fixtureDirectory;

        if (str_starts_with($relativePath, '@')) {
            if (!preg_match('#^@([A-Za-z][A-Za-z0-9_.-]*)/(.+)$#', $relativePath, $matches)) {
                throw new \RuntimeException(sprintf('Unsafe fixture asset alias: %s', $relativePath));
            }

            $alias        = $matches[1];
            $relativePath = $matches[2];
            $root         = $this->assetRoots[$alias] ?? '';

            if ($root === '') {
                throw new \RuntimeException(sprintf('Unknown fixture asset root: %s', $alias));
            }
        }

        $relativePath = str_replace('\\', '/', $relativePath);

        if (
            $relativePath === ''
            || str_starts_with($relativePath, '/')
            || str_contains($relativePath, "\0")
            || preg_match('#(^|/)\.\.(/|$)#', $relativePath)
            || preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $relativePath)
        ) {
            throw new \RuntimeException(sprintf('Unsafe fixture file path: %s', $relativePath));
        }

        $realRoot = realpath($root);
        $path     = $realRoot === false ? false : realpath($realRoot . '/' . $relativePath);

        if ($realRoot === false || $path === false || !str_starts_with($path, $realRoot . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException(sprintf('Fixture file is missing or outside fixture directory: %s', $relativePath));
        }

        return $path;
    }
}
