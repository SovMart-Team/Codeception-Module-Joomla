# Codeception Module for Joomla

A fixture-driven Codeception module for black-box functional testing of Joomla 6 applications through their real HTTP entry points.

The module keeps Cest methods focused on the scenario while method-owned PHP fixtures describe database state, identity and ACL, component configuration, request data, files, and expected results. Module-owned fixture mutations are journaled for cleanup. Rows created by the HTTP request in watched tables are removed. `MutationMode::Restore` can restore request-side updates while the target row still exists; request-side deletions require an application-specific database reset or a fresh database.

## Requirements

- PHP 8.2 or newer with PDO, PDO MySQL, JSON, and Fileinfo.
- Codeception 5.3 with the Db and PhpBrowser modules.
- Joomla 6.x.
- MariaDB for application-level functional tests. SQLite is used only by isolated package unit tests.

The original integration is verified against Joomla 6.1.1, Codeception 5.3.5, and MariaDB 11.4.

## Installation

Install the module as a development dependency:

```shell
composer require --dev sovmart/codeception-module-joomla
```

Until the first tagged release is available on Packagist, add this repository as a VCS repository:

```shell
composer config repositories.joomla-codeception vcs https://github.com/SovMart-Team/Codeception-Module-Joomla.git
composer require --dev sovmart/codeception-module-joomla:dev-main
```

## Quick start

Enable `PhpBrowser`, `Db`, and then the Joomla module in a suite:

```yaml
actor: AdministratorTester
modules:
    enabled:
        - PhpBrowser:
              url: 'http://joomla.test'
        - Db:
              dsn: 'mysql:host=db;dbname=joomla_test'
              user: 'joomla_test'
              password: 'secret'
              cleanup: false
              populate: false
        - JoomlaCodeception\Module\Joomla:
              client: administrator
              projectRoot: '/var/www/html'
              fixtureRoot: '/var/www/html/tests/Fixtures/Functional'
              fixtureNamespace: 'Tests\Fixtures\Functional'
              component: com_example
              componentAsset: com_example
              databasePrefix: jos_
              filesystemRoots:
                  images: '/var/www/html/images/example'
                  storage: '/var/www/html/storage'
                  tmp: '/var/www/html/tmp'
```

For this Cest:

```php
final class ArticleCest
{
    public function saveSucceeds(AdministratorTester $I): void
    {
        $I->loadDbFixtures();
        $I->loginFromFixture();
        $I->submitFormFromFixture(
            '/administrator/index.php?option=com_example&view=article&layout=edit',
            '#adminForm',
        );
        $I->seeResponseCodeIs(303);
        $I->checkResponseResults();
        $I->checkDbResults();
        $I->checkSideEffectResults();
    }
}
```

the module loads fixture classes from:

```text
tests/Fixtures/Functional/Administrator/Controller/Article/saveSucceeds/
```

Each method directory contains exactly one `RequestFixtureInterface` and one `ResponseResultInterface`. Optional classes add database rows, identity/ACL, configuration, database assertions, and filesystem assertions.

## What is included

- Administrator, Site, and API clients.
- Form, AJAX, JSON, generic HTTP, multipart, and Joomla Media requests.
- Database fixtures with cross-fixture references and deterministic rollback.
- Runtime Joomla identities, component ACL, API tokens, and component configuration.
- Filesystem setup and assertions under an explicit root allowlist.
- Response, database, filesystem, and no-side-effect assertions.
- Preflight validation, redacted diagnostics, and a MariaDB advisory lock for shared mutations.
- Project-specific authentication and CSRF adapters without forking the package.

## Documentation and examples

- [Getting started](docs/getting-started.md)
- [Fixture contracts and public actions](docs/fixture-contracts.md)
- [Authentication and CSRF adapters](docs/adapters.md)
- [Release and Packagist checklist](docs/releasing.md)
- [Two complete example scenarios](examples/README.md): a read-only API request and an authenticated Administrator form submission

The examples are distilled from the production Cest and method-owned fixture patterns used in the SovMart SaaS project, with project-specific routes and schemas replaced by `com_example` placeholders.

## Development

```shell
composer install
composer validate --strict
composer test
```

The package unit suite validates the example fixture contracts. Executing their HTTP flow still requires a disposable Joomla installation and MariaDB database.

## Versioning

Public contracts, DTO constructors, module actions, and configuration keys follow semantic versioning. The Composer manifest has no hardcoded version; Git tags are the package versions.

## License

[MIT](LICENSE)
