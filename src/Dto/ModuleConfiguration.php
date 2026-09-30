<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

final readonly class ModuleConfiguration
{
    /** @param array<string, string> $fixtureAssetRoots @param array<string, string> $filesystemRoots */
    public function __construct(
        public string $projectRoot,
        public string $fixtureRoot,
        public string $fixtureNamespace,
        public string $component,
        public string $componentAsset,
        public string $databasePrefix,
        public string $administratorLoginRoute,
        public string $administratorTokenRoute,
        public string $siteLoginRoute,
        public string $siteTokenRoute,
        public string $siteLoginSelector,
        public string $siteUsernameField,
        public string $sitePasswordField,
        public array $fixtureAssetRoots,
        public array $filesystemRoots,
        public string $publicImagePrefix,
        public string $mockBaseUrl,
        public string $authenticationAdapter,
        public string $csrfTokenProvider,
        public int $apiParentGroupId,
        public string $mutationLockName,
        public int $mutationLockTimeout,
    ) {
        foreach (['projectRoot', 'fixtureRoot', 'fixtureNamespace', 'component', 'componentAsset', 'databasePrefix'] as $property) {
            if ($this->{$property} === '') {
                throw new \RuntimeException(sprintf('Joomla module configuration %s cannot be empty.', $property));
            }
        }

        foreach (['projectRoot', 'fixtureRoot'] as $property) {
            $this->assertSafeAbsolutePath($this->{$property}, $property);
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $this->databasePrefix)) {
            throw new \RuntimeException('Joomla databasePrefix contains unsupported characters.');
        }

        foreach (['images', 'storage', 'tmp'] as $alias) {
            $root = $this->filesystemRoots[$alias] ?? null;

            if (!is_string($root) || $root === '') {
                throw new \RuntimeException(sprintf('Joomla filesystem root %s is missing.', $alias));
            }

            $this->assertSafeAbsolutePath($root, sprintf('filesystem root %s', $alias));
        }

        $roots = array_map(static fn (string $root): string => rtrim($root, '/'), array_intersect_key(
            $this->filesystemRoots,
            array_flip(['images', 'storage', 'tmp']),
        ));

        foreach ($roots as $leftAlias => $leftRoot) {
            foreach ($roots as $rightAlias => $rightRoot) {
                if ($leftAlias === $rightAlias) {
                    continue;
                }

                if ($leftRoot === $rightRoot || str_starts_with($leftRoot, $rightRoot . '/')) {
                    throw new \RuntimeException(sprintf(
                        'Joomla filesystem roots %s and %s must not overlap.',
                        $leftAlias,
                        $rightAlias,
                    ));
                }
            }
        }

        foreach ($this->fixtureAssetRoots as $alias => $root) {
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $alias)) {
                throw new \RuntimeException(sprintf('Invalid Joomla fixture asset root alias: %s', $alias));
            }

            if (!is_string($root)) {
                throw new \RuntimeException(sprintf('Joomla fixture asset root %s must be an absolute path.', $alias));
            }

            $this->assertSafeAbsolutePath($root, sprintf('fixture asset root %s', $alias));
        }

        if (!str_starts_with($this->publicImagePrefix, '/')) {
            throw new \RuntimeException('Joomla publicImagePrefix must be an absolute URL path.');
        }

        if ($this->apiParentGroupId <= 0) {
            throw new \RuntimeException('Joomla apiParentGroupId must be a positive integer.');
        }

        if ($this->mutationLockTimeout < 0) {
            throw new \RuntimeException('Joomla mutationLockTimeout cannot be negative.');
        }
    }

    private function assertSafeAbsolutePath(string $path, string $label): void
    {
        $normalized = str_replace('\\', '/', $path);

        if (
            !str_starts_with($normalized, '/')
            || rtrim($normalized, '/') === ''
            || str_contains($normalized, "\0")
            || preg_match('#(^|/)(?:\.|\.\.)(/|$)#', $normalized)
        ) {
            throw new \RuntimeException(sprintf('Joomla %s must be a safe absolute non-root path.', $label));
        }
    }
}
