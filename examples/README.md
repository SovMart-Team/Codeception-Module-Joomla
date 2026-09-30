# Complete examples

These two scenarios are simplified from the patterns used by the SovMart SaaS functional suite:

1. `StatusCest::returnsVersion()` demonstrates a public, read-only Joomla API request and JSON/database/filesystem no-side-effect checks.
2. `ArticleCest::saveSucceeds()` demonstrates database setup, a temporary Administrator identity, a real Joomla form submission, a redirect expectation, and a database result assertion.

They use an imaginary `com_example` component so the package does not depend on the SovMart schema. Copy the files into a consuming project and change routes, tables, fields, ACL actions, and namespaces to match that project.

## Layout in this repository

The example config is directly usable with the sibling `Functional` and `Fixtures` directories. Set the Joomla, database, and filesystem variables for your disposable installation as shown below, but point `TEST_FIXTURE_ROOT` at this repository's example fixtures:

```shell
export TEST_FIXTURE_ROOT="$PWD/examples/Fixtures/Functional"
composer dump-autoload
vendor/bin/codecept config:validate -c examples
vendor/bin/codecept build -c examples
```

## Layout after copying into a project

```text
tests/
├── Functional/
│   ├── Administrator.suite.yml
│   ├── Administrator/Controller/ArticleCest.php
│   ├── Api.suite.yml
│   └── Api/Controller/StatusCest.php
└── Fixtures/Functional/
    ├── Administrator/Controller/Article/saveSucceeds/*.php
    └── Api/Controller/Status/returnsVersion/*.php
```

After copying, the example suite files use environment parameters. Supply at least:

```dotenv
TEST_BASE_URL=http://joomla.test
TEST_ROOT=/var/www/html
TEST_FIXTURE_ROOT=/var/www/html/tests/Fixtures/Functional
TEST_IMAGES_ROOT=/var/www/html/images/example
TEST_STORAGE_ROOT=/var/www/html/storage
TEST_TMP_ROOT=/var/www/html/tmp
DB_HOST=db
DB_NAME=joomla_test
DB_USER=joomla_test
DB_PASSWORD=secret
DB_PREFIX=jos_
```

Register the fixture namespace in the consuming project's `composer.json`:

```json
{
    "autoload-dev": {
        "psr-4": {
            "Example\\Tests\\Fixtures\\Functional\\": "tests/Fixtures/Functional/"
        }
    }
}
```

Copy files as follows:

| Package path | Project path |
| --- | --- |
| `examples/codeception.yml` | `codeception.yml` |
| `examples/Functional/*` | `tests/Functional/` |
| `examples/Fixtures/Functional/*` | `tests/Fixtures/Functional/` |

In the copied root config, change its four paths to `tests/Functional`, `tests/Functional/_output`, `tests/Fixtures/Functional`, and `tests/Functional/Support`. Then regenerate the Composer autoloader and Codeception actors:

```shell
composer dump-autoload
vendor/bin/codecept config:validate
vendor/bin/codecept build
```
