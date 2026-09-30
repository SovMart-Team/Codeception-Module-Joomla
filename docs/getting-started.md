# Getting started

This guide assumes an existing Codeception 5 project that can reach a disposable Joomla 6 installation over HTTP and connect directly to the same MariaDB database.

## 1. Install and autoload tests

```shell
composer require --dev sovmart/codeception-module-joomla
```

Add the namespaces used by your Cest and fixture classes to the consuming project's `composer.json`:

```json
{
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/",
            "Vendor\\Project\\Tests\\Fixtures\\Functional\\": "tests/Fixtures/Functional/"
        },
        "exclude-from-classmap": [
            "tests/Functional/"
        ]
    }
}
```

Regenerate the autoloader after changing the map:

```shell
composer dump-autoload
```

## 2. Configure Codeception

A minimal root `codeception.yml`:

```yaml
namespace: Tests\Functional
support_namespace: Support
paths:
    tests: tests/Functional
    output: tests/Functional/_output
    data: tests/Fixtures/Functional
    support: tests/Functional/Support
actor_suffix: Tester
params:
    - env
```

An Administrator suite:

```yaml
actor: AdministratorTester
modules:
    enabled:
        - PhpBrowser:
              url: '%TEST_BASE_URL%'
        - Db:
              dsn: 'mysql:host=%DB_HOST%;dbname=%DB_NAME%'
              user: '%DB_USER%'
              password: '%DB_PASSWORD%'
              cleanup: false
              populate: false
        - JoomlaCodeception\Module\Joomla:
              client: administrator
              projectRoot: '%TEST_ROOT%'
              fixtureRoot: '%TEST_FIXTURE_ROOT%'
              fixtureNamespace: 'Vendor\Project\Tests\Fixtures\Functional'
              component: com_example
              componentAsset: com_example
              databasePrefix: '%DB_PREFIX%'
              filesystemRoots:
                  images: '%TEST_IMAGES_ROOT%'
                  storage: '%TEST_STORAGE_ROOT%'
                  tmp: '%TEST_TMP_ROOT%'
              publicImagePrefix: '/images/example'
              mutationLockName: example-codeception-fixtures
              mutationLockTimeout: 60
```

`client` is one of `administrator`, `site`, or `api`. Create one suite for each client because discovery includes the client name in the fixture path.

The `Db` module must use the same Joomla database and table prefix as the application. Its own `cleanup` and `populate` features stay disabled: the Joomla module owns method-level setup and rollback.

All filesystem roots are an allowlist. The paths must point to the same files seen by the tested Joomla process. This matters when Codeception and Joomla run in different containers.

## 3. Generate actors

After adding or changing modules, rebuild generated Tester actions:

```shell
vendor/bin/codecept build
```

## 4. Add a Cest and its fixtures

Given this class and method:

```text
tests/Functional/Administrator/Controller/ArticleCest.php
Tests\Functional\Administrator\Controller\ArticleCest::saveSucceeds()
```

the module looks for:

```text
tests/Fixtures/Functional/
└── Administrator/
    └── Controller/
        └── Article/
            └── saveSucceeds/
                ├── DatabaseFixture.php
                ├── IdentityFixture.php
                ├── RequestFixture.php
                └── ResponseResult.php
```

The last namespace segment of the Cest (`Controller`), the class name without `Cest` (`Article`), and the method name form the lookup path. Fixture namespaces mirror the path below `fixtureRoot`.

Start with the complete files in [`examples`](../examples/README.md), then replace `com_example`, routes, table names, permissions, and namespaces with project values.

## 5. Run tests

```shell
vendor/bin/codecept run Administrator --debug
vendor/bin/codecept run Api --debug
```

Run application-level tests only against an isolated database and filesystem. The module restores its fixture mutations and removes rows created by requests in watched tables. It does not generally restore arbitrary request-side updates or deletions.

The canonical scenario order is:

```php
$I->applyConfigurationFixtures(); // When ConfigurationFixture exists.
$I->loadDbFixtures();             // Also creates IdentityFixture identities.
$I->loadFileFixtures();           // When preparedFiles are configured.
$I->loginFromFixture();           // For authenticated scenarios.

// Perform exactly one request action here.
$I->seeResponseCodeIs(200);        // Keep the expected HTTP status explicit.
$I->checkResponseResults();
$I->checkDbResults();
$I->checkFileResults();
$I->checkSideEffectResults();
```

## Configuration reference

| Key | Required | Purpose |
| --- | --- | --- |
| `client` | yes | `administrator`, `site`, or `api`. |
| `projectRoot` | yes | Joomla root containing `configuration.php`. |
| `fixtureRoot` | yes | Absolute root of method-owned fixtures. |
| `fixtureNamespace` | yes | Namespace that mirrors `fixtureRoot`. |
| `component` | yes | Component option used for configuration, for example `com_example`. |
| `componentAsset` | yes | Joomla ACL asset name. |
| `databasePrefix` | yes | Physical table prefix used to resolve fixture `#__` names. |
| `filesystemRoots` | yes | Absolute `images`, `storage`, and `tmp` roots. |
| `fixtureAssetRoots` | no | Extra read-only source roots such as generated security corpora. |
| `administratorLoginRoute` | no | Administrator login form; default `/administrator/index.php`. |
| `administratorTokenRoute` | no | Administrator HTML page containing a hidden Joomla token; default `/administrator/index.php`. |
| `siteLoginRoute` | no | Site login form; default Joomla users login route. |
| `siteTokenRoute` | no | Site HTML page containing a hidden Joomla token; default `/`. |
| `siteLoginSelector` | no | Site login form selector; default `form#login-form`. |
| `siteUsernameField` | no | Site username field; default `username`. |
| `sitePasswordField` | no | Site password field; default `password`. |
| `publicImagePrefix` | no | URL prefix mapped to the `images` root; default `/images`. |
| `mockBaseUrl` | no | Base URL used when a request declares `mockCase`. |
| `authenticationAdapter` | no | Class implementing `AuthenticationAdapterInterface`. |
| `csrfTokenProvider` | no | Class implementing `CsrfTokenProviderInterface`. |
| `apiParentGroupId` | no | Parent group for generated API identities; default `8` (Super Users, allowed by Joomla token authentication by default). Configure Joomla's token plugin and this value together for least-privilege API tests. |
| `mutationLockName` | no | MariaDB advisory lock name. |
| `mutationLockTimeout` | no | Lock timeout in seconds; default `30`. |

Filesystem roots must be absolute, non-root, and non-overlapping. Project, fixture, and asset paths reject `.`/`..` segments. The database prefix accepts only letters, numbers, and underscores and must begin with a letter or underscore.

## Troubleshooting

- `Suite ... was not loaded`: run the suite name from its `*.suite.yml`, for example `codecept run Administrator`.
- `Fixture directory is missing`: verify `Client/LastCestNamespaceSegment/ClassWithoutCest/method`; paths and case must match exactly.
- `Capability file does not declare expected class`: the fixture namespace must mirror the path below `fixtureRoot`.
- `Unable to find Joomla CSRF token`: point the relevant token route at an HTML page containing Joomla's 32-character hidden token field.
- Fixtures are visible to the runner but not Joomla: mount the same volumes and configure `filesystemRoots` using runner-visible absolute paths that map to Joomla's runtime directories.
- Expected `Location` header is missing: use the default `RedirectPolicy::Stop`; following redirects changes assertions to the final response.
- Advisory lock acquisition fails: verify the MariaDB connection, permissions, lock timeout, and that parallel test installations use distinct lock names.
- API login fails: provide a project authentication adapter, make `configuration.php` readable to the runner, and align `apiParentGroupId` with the Joomla token plugin's allowed groups.
- `JConfig is already loaded from another Joomla root`: run suites for different Joomla installations in separate PHP processes.
