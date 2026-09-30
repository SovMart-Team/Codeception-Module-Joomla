# Changelog

All notable changes follow Keep a Changelog. Releases use semantic version tags.

## Unreleased

- Extracted the fixture-driven Joomla Codeception module into a reusable package.
- Added configurable project paths, component metadata, adapters and filesystem roots.
- Added MariaDB-safe mutation locking, full preflight validation and deterministic cleanup.
- Added generic HTTP methods and Administrator, Site and API clients.
- Added deterministic Joomla API fixture identities with `core.login.api`, token profiles and runtime Bearer tokens.
- Added standalone Composer metadata, CI, package documentation and complete API/Administrator examples.
- Exposed the generated API Bearer token through the documented `identity.apiToken` runtime fixture value.
- Hardened configuration paths, database prefixes, secret redaction and retryable cleanup.
- Defaulted generated API identities to Joomla's token-enabled Super Users group with a configurable parent group.
- Added verified example contracts, troubleshooting and a release/Packagist checklist.
