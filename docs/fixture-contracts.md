# Fixture contracts and public actions

Each Cest method owns a directory of small PHP classes. This keeps HTTP flow visible in the Cest while setup and assertions remain typed and reusable by the module lifecycle.

## Required capabilities

Every method directory must contain exactly one implementation of each:

- `RequestFixtureInterface`, in a class whose name ends with `Fixture`.
- `ResponseResultInterface`, in a class whose name ends with `Result`.

Optional setup contracts are `FixtureInterface` for database rows, `IdentityFixtureInterface` for a temporary user and ACL, and `ConfigurationFixtureInterface` for component parameters.

Optional result contracts are `SeeInDatabaseInterface`, `DontSeeInDatabaseInterface`, `CountRecordsInterface`, `DatabaseStoredFileResultInterface`, `SeeFileInterface`, `DontSeeFileInterface`, and `CountFilesInterface`. A result class may implement several result contracts, but a class cannot mix setup and result phases.

The loader considers only files named `*Fixture.php` or `*Result.php`. The class name must equal the filename.

## Database fixtures

Return a list of `DatabaseFixture` objects. Tables always use Joomla `#__` placeholders:

```php
public function getFixturesData(FixtureProviderInterface $fixtures): array
{
    return [
        new DatabaseFixture(
            key: 'category',
            table: '#__example_categories',
            values: ['title' => 'Functional test category', 'published' => 1],
        ),
        new DatabaseFixture(
            key: 'article',
            table: '#__example_articles',
            values: [
                'category_id' => $fixtures->reference(self::class, 'category'),
                'title' => 'Draft article',
            ],
        ),
    ];
}
```

`MutationMode::Insert` deletes the created row during cleanup. `Update` and `Restore` require bounded equality criteria and restore prior state. Fixture references are resolved after the referenced row is inserted; the default referenced column is its primary key.

Result criteria may also use `DatabaseCondition` operators: equal/not-equal, null/not-null, in/not-in, like/not-like, and numeric comparisons. `semanticJson: true` compares decoded JSON instead of raw strings.

## Identity and configuration

An identity fixture creates a temporary Joomla group and user, applies root and component ACL, and removes them after the scenario:

```php
public function rootPermissions(): array
{
    return ['core.login.admin' => 1];
}

public function componentPermissions(): array
{
    return ['core.manage' => 1, 'core.create' => 1];
}
```

ACL values are limited to `-1`, `0`, and `1`. Runtime values are available through the fixture provider:

- `identity.userId`
- `identity.username`
- `identity.email`
- `identity.password`
- `identity.apiToken` for API identities

`ConfigurationFixtureInterface::configuration()` returns component parameters. Existing parameters are restored after the scenario.

## Request data

`RequestFixtureInterface::getRequest()` returns `RequestData`. Its main properties are:

- `fields`: query, form, or request data. Values may contain fixture references and runtime values.
- `csrfMode`: `Auto`, `Form`, `Query`, `Json`, `Header`, `Invalid`, or `Missing`.
- `controlFieldPolicy`: controls how discovered hidden form fields interact with fixture fields.
- `redirectPolicy`: stop or follow redirects.
- `task`: Joomla task injected during form submission.
- `selection`: a typed `ListSelection` that produces `cid` and `boxchecked`.
- `preparedFiles`: files copied into an allowlisted runtime root before the request.
- `upload`/`uploads`: multipart upload definitions.
- `adapterPath` and `content`: Joomla Media API path and source body.
- `mockCase`: a case name appended to the configured mock service URL.

CSRF placement is explicit when a scenario needs something other than the default:

| `CsrfMode` | Behavior |
| --- | --- |
| `Auto` / `Form` | Add the authenticated session token to request fields; HTML form submission uses the token discovered in the form. |
| `Query` | Put the discovered token in the request query string. |
| `Json` | Add the token to request fields, which JSON actions serialize into the JSON body. |
| `Header` | Send the token through `X-CSRF-Token`. |
| `Invalid` | Send a deliberately invalid token for a negative scenario. |
| `Missing` | Omit the token for a negative scenario. |

`controlFieldPolicy` applies to HTML form submission:

| `ControlFieldPolicy` | Behavior |
| --- | --- |
| `Merge` | Merge discovered hidden controls with fixture fields; fixture values win. |
| `FixtureOnly` | Submit only fixture fields. |
| `Remove` | Remove discovered control names from the fixture fields before submission. |

For list controllers, use `ListSelection` instead of manually composing `cid` and `boxchecked`:

```php
return new RequestData(
    selection: new ListSelection([
        $fixtures->reference(ArticleFixture::class, 'first'),
        $fixtures->reference(ArticleFixture::class, 'second'),
    ]),
    task: 'articles.publish',
    csrfMode: CsrfMode::Form,
);
```

## Files and uploads

`FixtureFile` sources are relative to the current method directory. Prepared targets and result assertions are restricted to configured `images`, `storage`, and `tmp` roots:

```php
return new RequestData(
    preparedFiles: [
        new FixtureFile('files/source.json', root: 'storage', relativePath: 'cases/source.json'),
    ],
    uploads: [
        new UploadFile(
            'files/image.png',
            inputName: 'jform[image]',
            uploadName: 'image.png',
            clientMime: 'image/png',
            sourceMime: 'image/png',
            sourceMinBytes: 1,
            sourceMaxBytes: 1048576,
        ),
    ],
);
```

Named `fixtureAssetRoots` may supply generated read-only sources such as `@security/images/valid.png`. Absolute paths, `..`, symlink escapes, missing files, and unknown aliases fail during preflight.

## Results and side effects

`ResponseExpectation` can assert HTML/JSON/binary type, content type, redirect location, login form, required or forbidden fragments, uploaded image metadata, and watched tables.

If `noSideEffects` is true, call `checkSideEffectResults()` after the request. The module compares the watched database tables and configured filesystem roots with their pre-request snapshots. An empty `watchedTables` list checks only filesystem state.

Watched tables also drive cleanup of rows created by the HTTP request. List them parent-first so cleanup can delete them in reverse dependency order. A watched table should have a primary key. Request-side changes are not restored from this snapshot. `MutationMode::Restore` can restore an update while its target row still exists. A request-side deletion requires an application-specific database reset or a fresh database.

## Public actions

| Action | Purpose |
| --- | --- |
| `applyConfigurationFixtures()` | Apply component parameter fixtures. |
| `loadDbFixtures()` | Create identity when needed and apply database fixtures. |
| `loadFileFixtures()` | Materialize prepared files. |
| `loginFromFixture()` | Authenticate the temporary identity. |
| `openPageFromFixture($route)` | Send GET with fixture query data. |
| `sendAjaxPostFromFixture($route)` | Send Joomla AJAX POST. |
| `sendJsonFromFixture($route)` | Send JSON POST. |
| `sendRequestFromFixture($method, $route)` | Send GET, POST, PUT, PATCH, or DELETE. |
| `submitFormFromFixture($page, $selector)` | Load an HTML form, process hidden controls according to `controlFieldPolicy`, and submit it. |
| `submitMultipartFromFixture($route)` | Send multipart fields and uploads. |
| `submitMultipartFromFixtureWithUpload($route, $upload)` | Add one validated runtime upload to the fixture request. |
| `sendMediaFromFixture($route)` | Send a Joomla Media JSON request. |
| `checkResponseResults()` | Check `ResponseExpectation`. |
| `checkDbResults()` | Run database result contracts. |
| `checkFileResults()` | Run filesystem and database-stored-file contracts. |
| `checkSideEffectResults()` | Verify a no-side-effect snapshot. |
| `getRawFixture($path)` | Read a safe method-local source file. |
| `getJsonFixture($path)` | Read and decode a method-local JSON file. |

Call only setup actions for capabilities present in the method directory. Cleanup runs automatically in Codeception's `_after` hook and immediately when a module action throws.

Request actions differ as follows:

| Action | Transport semantics |
| --- | --- |
| `openPageFromFixture` | GET with resolved fields in the query string. |
| `sendAjaxPostFromFixture` | Form-style AJAX POST. |
| `sendJsonFromFixture` | JSON POST with an XMLHttpRequest header. |
| `sendRequestFromFixture` | GET query or parameter body for POST/PUT/PATCH/DELETE. |
| `submitFormFromFixture` | Loads the page first, reads form action/method and hidden controls, then submits. |
| `submitMultipartFromFixture` | Multipart POST with fixture fields and files. |

`RedirectPolicy::Stop` is the default and is required when asserting the response `Location` header. `Follow` changes response assertions to the final response after redirects.

## Parallel execution

The default MariaDB integration serializes global Joomla ACL, configuration, user/group, and fixture mutations with an advisory lock. For high-throughput parallel suites, use a separate Joomla installation and database per worker.
