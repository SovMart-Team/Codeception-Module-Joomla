# Authentication and CSRF adapters

The default adapter supports standard Joomla administrator and site login forms. API authentication and customized login flows belong in the consuming project.

## Authentication adapter

Implement `AuthenticationAdapterInterface` and configure its class name as `authenticationAdapter` in the suite:

```php
<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Contract\AuthenticationAdapterInterface;
use JoomlaCodeception\Dto\JoomlaClient;
use JoomlaCodeception\Dto\ModuleConfiguration;

final readonly class AuthenticationAdapter implements AuthenticationAdapterInterface
{
    public function login(
        PhpBrowser $browser,
        JoomlaClient $client,
        array $identity,
        ModuleConfiguration $configuration,
    ): void {
        if ($client === JoomlaClient::Api) {
            // deleteHeader() keeps this adapter compatible with PhpBrowser 4.0.
            $browser->deleteHeader('Authorization');
            $browser->haveHttpHeader('Authorization', 'Bearer ' . $identity['apiToken']);

            return;
        }

        // Implement a project-specific form or SSO flow here.
    }
}
```

The identity array contains `userId`, `username`, `email`, `password`, `groupId`, and `apiToken`. `apiToken` is non-empty only for the API client. Never write it to fixture files, diagnostics, or test artifacts.

PhpBrowser can print request headers in debug output. Do not run authenticated API scenarios with verbose HTTP logging on shared CI, and clear any persistent `Authorization` header in the adapter before switching to a guest scenario. Run different Joomla roots in separate Codeception processes so their global `JConfig` classes cannot mix.

## CSRF token provider

`HtmlCsrfTokenProvider` is the default and discovers Joomla's token through the configured token page. Projects that require session-backed resolution can select `MariaDbSessionCsrfTokenProvider` or implement `CsrfTokenProviderInterface`:

```php
<?php

declare(strict_types=1);

namespace Tests\Functional\Support;

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Contract\CsrfTokenProviderInterface;
use JoomlaCodeception\Dto\JoomlaClient;
use JoomlaCodeception\Dto\ModuleConfiguration;
use JoomlaCodeception\Service\DatabaseFixtureManager;

final readonly class CsrfTokenProvider implements CsrfTokenProviderInterface
{
    public function token(
        PhpBrowser $browser,
        \PDO $database,
        DatabaseFixtureManager $fixtures,
        JoomlaClient $client,
        ?int $userId,
        ModuleConfiguration $configuration,
    ): string {
        // Resolve and return the 32-character Joomla token field name.
        throw new \LogicException('Implement the project-specific CSRF lookup.');
    }
}
```

Configure it by fully qualified class name:

```yaml
- JoomlaCodeception\Module\Joomla:
      authenticationAdapter: 'Tests\Functional\Support\AuthenticationAdapter'
      csrfTokenProvider: 'Tests\Functional\Support\CsrfTokenProvider'
```

## Compatibility boundary

`PhpBrowserTransport` and `CodeceptionDbConnectionProvider` are the only bridges to Codeception internal APIs (`_request`, the response connector, and `_getDbh`). If a future Codeception version removes those APIs, the module throws `UnsupportedEnvironmentException` instead of silently changing behavior.
