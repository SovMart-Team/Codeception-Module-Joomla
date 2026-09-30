<?php

declare(strict_types=1);

namespace JoomlaCodeception\Context;

final readonly class FixtureCapabilityLoader
{
    public function __construct(private string $fixtureRoot, private string $fixtureNamespace)
    {
    }

    /** @return list<object> */
    public function load(string $directory): array
    {
        $paths = array_merge(
            glob($directory . '/*Fixture.php') ?: [],
            glob($directory . '/*Result.php') ?: [],
        );
        sort($paths, SORT_STRING);

        if ($paths === []) {
            throw new \RuntimeException(sprintf('Fixture directory contains no *Fixture.php or *Result.php classes: %s', $directory));
        }

        $capabilities = [];

        foreach ($paths as $path) {
            $class = $this->className($path);

            require_once $path;

            if (!class_exists($class, false)) {
                throw new \RuntimeException(sprintf('Capability file does not declare expected class %s: %s', $class, $path));
            }

            $capabilities[] = new $class();
        }

        return $capabilities;
    }

    private function className(string $path): string
    {
        $testsRoot     = rtrim($this->fixtureRoot, '/') . '/';
        $realPath      = realpath($path);
        $realTestsRoot = realpath($testsRoot);

        if ($realPath === false || $realTestsRoot === false || !str_starts_with($realPath, $realTestsRoot . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException(sprintf('Capability is outside tests root: %s', $path));
        }

        $relative = substr($realPath, strlen($realTestsRoot) + 1, -4);

        return trim($this->fixtureNamespace, '\\') . '\\' . str_replace('/', '\\', $relative);
    }
}
