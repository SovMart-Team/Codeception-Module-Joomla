<?php

declare(strict_types=1);

namespace JoomlaCodeception\Context;

use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Dto\FixtureFile;
use JoomlaCodeception\Dto\FixtureReference;

final class ValidationFixtureProvider implements FixtureProviderInterface
{
    /** @var list<FixtureReference> */
    private array $references = [];

    /** @var list<string> */
    private array $values = [];

    public function reference(string $fixtureClass, string $key, string $column = 'id'): FixtureReference
    {
        $reference          = new FixtureReference($fixtureClass, $key, $column);
        $this->references[] = $reference;

        return $reference;
    }

    public function value(string $name): mixed
    {
        $this->values[] = $name;

        return str_ends_with($name, 'Id') ? 1 : 'fixture-validation-value';
    }

    public function file(string $relativePath): FixtureFile
    {
        return new FixtureFile($relativePath);
    }

    /** @return list<FixtureReference> */
    public function references(): array
    {
        return $this->references;
    }

    /** @return list<string> */
    public function values(): array
    {
        return $this->values;
    }
}
