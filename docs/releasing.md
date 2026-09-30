# Releasing and Packagist

The package version comes from Git tags; `composer.json` intentionally contains no `version` field.

## First publication

1. Commit the package files and push the default `main` branch to `SovMart-Team/Codeception-Module-Joomla`.
2. Verify the repository and issue URLs from a clean machine.
3. Run the release checks below.
4. Choose the initial semantic version. Use `0.x` while public contracts may still change; use `1.0.0` only when the documented API is considered stable.
5. Move the relevant `Unreleased` changelog entries under `## [x.y.z] - YYYY-MM-DD` and commit that change.
6. Create and push an annotated `vX.Y.Z` tag, then create the matching GitHub release.
7. Submit `https://github.com/SovMart-Team/Codeception-Module-Joomla` to Packagist under the `sovmart` vendor and enable its GitHub hook.
8. Verify installation from a new directory with `composer require --dev sovmart/codeception-module-joomla:^X.Y`.

Do not create a tag or Packagist entry before the repository has at least one pushed commit. Composer cannot derive a reliable VCS version from an empty repository.

## Release checks

```shell
composer validate --strict
composer update --prefer-lowest --prefer-dist --no-interaction
composer test
composer update --prefer-dist --no-interaction
composer audit
composer archive --format=tar
```

Inspect the archive and confirm it contains source, documentation, examples, license, security policy, and changelog, but not `vendor`, `composer.lock`, package tests, CI, IDE files, or PHPUnit cache.

Before a stable release, also run the Administrator, Site, and API functional suites against a clean supported Joomla/MariaDB environment. In particular, verify API token authentication using Joomla's actual token plugin configuration and verify the application's database reset strategy for request-side updates and deletes.
