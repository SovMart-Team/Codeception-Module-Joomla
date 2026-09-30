<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

use JoomlaCodeception\Dto\FixtureFile;
use JoomlaCodeception\Dto\FixtureReference;

interface FixtureProviderInterface
{
    public function reference(string $fixtureClass, string $key, string $column = 'id'): FixtureReference;

    public function value(string $name): mixed;

    public function file(string $relativePath): FixtureFile;
}
