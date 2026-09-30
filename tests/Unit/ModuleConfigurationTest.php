<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

use JoomlaCodeception\Dto\ModuleConfiguration;
use PHPUnit\Framework\TestCase;

final class ModuleConfigurationTest extends TestCase
{
    public function testAcceptsSafeStandaloneConfiguration(): void
    {
        $configuration = $this->configuration();

        self::assertSame(8, $configuration->apiParentGroupId);
    }

    public function testRejectsRelativeFilesystemRoot(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('safe absolute non-root path');

        $this->configuration(['images' => 'images/example', 'storage' => '/srv/storage', 'tmp' => '/srv/tmp']);
    }

    public function testRejectsOverlappingFilesystemRoots(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must not overlap');

        $this->configuration(['images' => '/srv/app', 'storage' => '/srv/app/storage', 'tmp' => '/srv/tmp']);
    }

    public function testRejectsUnsafeDatabasePrefix(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('databasePrefix');

        $this->configuration(databasePrefix: 'jos_`');
    }

    /** @param array<string, string>|null $filesystemRoots */
    private function configuration(?array $filesystemRoots = null, string $databasePrefix = 'jos_'): ModuleConfiguration
    {
        return new ModuleConfiguration(
            projectRoot: '/srv/joomla',
            fixtureRoot: '/srv/tests/Fixtures/Functional',
            fixtureNamespace: 'Example\\Tests\\Fixtures\\Functional',
            component: 'com_example',
            componentAsset: 'com_example',
            databasePrefix: $databasePrefix,
            administratorLoginRoute: '/administrator/index.php',
            administratorTokenRoute: '/administrator/index.php',
            siteLoginRoute: '/index.php?option=com_users&view=login',
            siteTokenRoute: '/',
            siteLoginSelector: 'form#login-form',
            siteUsernameField: 'username',
            sitePasswordField: 'password',
            fixtureAssetRoots: [],
            filesystemRoots: $filesystemRoots ?? [
                'images' => '/srv/images/example',
                'storage' => '/srv/storage',
                'tmp' => '/srv/tmp',
            ],
            publicImagePrefix: '/images/example',
            mockBaseUrl: '',
            authenticationAdapter: 'Example\\AuthenticationAdapter',
            csrfTokenProvider: 'Example\\CsrfTokenProvider',
            apiParentGroupId: 8,
            mutationLockName: 'example-fixtures',
            mutationLockTimeout: 30,
        );
    }
}
