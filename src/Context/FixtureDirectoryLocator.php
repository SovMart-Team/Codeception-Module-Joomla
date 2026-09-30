<?php

declare(strict_types=1);

namespace JoomlaCodeception\Context;

use JoomlaCodeception\Dto\JoomlaClient;

final readonly class FixtureDirectoryLocator
{
    public function __construct(private string $fixtureRoot)
    {
    }

    public function locate(JoomlaClient $client, object $cest, string $method): string
    {
        $reflection = new \ReflectionClass($cest);
        $cestName   = $reflection->getShortName();

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*Cest$/', $cestName)) {
            throw new \RuntimeException(sprintf('Unsupported Cest class: %s', $cestName));
        }

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $method)) {
            throw new \RuntimeException(sprintf('Invalid Cest method: %s', $method));
        }

        $namespace = explode('\\', $reflection->getNamespaceName());
        $layer     = end($namespace);

        if (!is_string($layer) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $layer)) {
            throw new \RuntimeException(sprintf('Unable to resolve fixture layer for %s.', $reflection->getName()));
        }

        $directory = sprintf(
            '%s/%s/%s/%s/%s',
            rtrim($this->fixtureRoot, '/'),
            match ($client) {
                JoomlaClient::Administrator => 'Administrator',
                JoomlaClient::Site          => 'Site',
                JoomlaClient::Api           => 'Api',
            },
            $layer,
            substr($cestName, 0, -4),
            $method,
        );

        if (!is_dir($directory)) {
            throw new \RuntimeException(sprintf('Fixture directory is missing: %s', $directory));
        }

        return $directory;
    }
}
