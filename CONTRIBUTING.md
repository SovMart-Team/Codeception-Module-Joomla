# Contributing

## Local setup

```shell
composer install
composer validate --strict
composer test
```

PHP 8.2 is the minimum supported runtime. Keep production code compatible with the PHP and Codeception constraints declared in `composer.json`.

## Changes

- Keep Joomla-, component-, route-, namespace-, and filesystem-specific values in suite configuration or project adapters.
- Add unit coverage for fixture validation, DTO behavior, cleanup, or Codeception compatibility bridges when those areas change.
- Do not expose passwords, session data, API tokens, or complete authorization headers in diagnostics.
- Document public contract, DTO constructor, module action, and configuration changes in `CHANGELOG.md`.
- Treat backward-incompatible public API changes as a new major version.

Application-level changes should also be exercised against a disposable Joomla installation with MariaDB.
