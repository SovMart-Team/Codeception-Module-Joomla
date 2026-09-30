<?php

declare(strict_types=1);

namespace JoomlaCodeception\Context;

use JoomlaCodeception\Contract\ConfigurationFixtureInterface;
use JoomlaCodeception\Contract\CountFilesInterface;
use JoomlaCodeception\Contract\CountRecordsInterface;
use JoomlaCodeception\Contract\DatabaseStoredFileResultInterface;
use JoomlaCodeception\Contract\DontSeeFileInterface;
use JoomlaCodeception\Contract\DontSeeInDatabaseInterface;
use JoomlaCodeception\Contract\FixtureInterface;
use JoomlaCodeception\Contract\IdentityFixtureInterface;
use JoomlaCodeception\Contract\RequestFixtureInterface;
use JoomlaCodeception\Contract\ResponseResultInterface;
use JoomlaCodeception\Contract\SeeFileInterface;
use JoomlaCodeception\Contract\SeeInDatabaseInterface;
use JoomlaCodeception\Dto\DatabaseCondition;
use JoomlaCodeception\Dto\DatabaseCountExpectation;
use JoomlaCodeception\Dto\DatabaseCriteria;
use JoomlaCodeception\Dto\DatabaseFixture;
use JoomlaCodeception\Dto\DatabaseStoredFileExpectation;
use JoomlaCodeception\Dto\FileCountExpectation;
use JoomlaCodeception\Dto\FileExpectation;
use JoomlaCodeception\Dto\FixtureFile;
use JoomlaCodeception\Dto\FixtureReference;
use JoomlaCodeception\Dto\ListSelection;
use JoomlaCodeception\Dto\MutationMode;
use JoomlaCodeception\Dto\RequestData;
use JoomlaCodeception\Dto\ResponseExpectation;
use JoomlaCodeception\Dto\UploadFile;
use JoomlaCodeception\Exception\FixtureValidationException;
use JoomlaCodeception\Service\FixtureProvider;

final class FixtureContractValidator
{
    /** @var list<FixtureReference> */
    private array $encounteredReferences = [];

    /** @param list<object> $capabilities @param array<string, string> $assetRoots */
    public function validate(array $capabilities, string $directory, array $assetRoots = []): void
    {
        $this->encounteredReferences = [];
        $setupInterfaces             = [
            ConfigurationFixtureInterface::class,
            FixtureInterface::class,
            IdentityFixtureInterface::class,
            RequestFixtureInterface::class,
        ];
        $resultInterfaces = [
            ResponseResultInterface::class,
            SeeInDatabaseInterface::class,
            DontSeeInDatabaseInterface::class,
            CountRecordsInterface::class,
            DatabaseStoredFileResultInterface::class,
            SeeFileInterface::class,
            DontSeeFileInterface::class,
            CountFilesInterface::class,
        ];

        foreach ($capabilities as $capability) {
            $shortName        = (new \ReflectionClass($capability))->getShortName();
            $implementedSetup = array_values(array_filter(
                $setupInterfaces,
                static fn (string $interface): bool => $capability instanceof $interface,
            ));
            $implementedResult = array_values(array_filter(
                $resultInterfaces,
                static fn (string $interface): bool => $capability instanceof $interface,
            ));
            $validFixture = str_ends_with($shortName, 'Fixture')
                && $implementedSetup !== []
                && $implementedResult === [];
            $validResult = str_ends_with($shortName, 'Result')
                && $implementedResult !== []
                && $implementedSetup === [];

            if (!$validFixture && !$validResult) {
                throw new FixtureValidationException(sprintf(
                    'Fixture/result %s must implement contracts from exactly one phase compatible with its suffix in %s.',
                    $capability::class,
                    $directory,
                ));
            }
        }

        $this->assertCount($capabilities, RequestFixtureInterface::class, 1, true, $directory);
        $this->assertCount($capabilities, ResponseResultInterface::class, 1, true, $directory);
        $this->assertCount($capabilities, IdentityFixtureInterface::class, 1, false, $directory);
        $this->assertCount($capabilities, ConfigurationFixtureInterface::class, 1, false, $directory);

        $validationProvider = new ValidationFixtureProvider();
        $fileProvider       = new FixtureProvider($directory, $assetRoots);

        foreach ($capabilities as $capability) {
            try {
                $this->validateCapability($capability, $validationProvider, $fileProvider);
            } catch (\Throwable $throwable) {
                if ($throwable instanceof FixtureValidationException) {
                    throw $throwable;
                }

                throw new FixtureValidationException(sprintf(
                    'Invalid fixture/result data from %s in %s: %s',
                    $capability::class,
                    $directory,
                    $throwable->getMessage(),
                ), 0, $throwable);
            }
        }

        $fixtureKeys = [];

        foreach ($capabilities as $capability) {
            if (!$capability instanceof FixtureInterface) {
                continue;
            }

            foreach ($capability->getFixturesData($validationProvider) as $fixture) {
                if ($fixture instanceof DatabaseFixture) {
                    $fixtureKeys[$capability::class][$fixture->key] = true;
                }
            }
        }

        $references = [...$validationProvider->references(), ...$this->encounteredReferences];

        foreach ($references as $reference) {
            if (!isset($fixtureKeys[$reference->fixtureClass][$reference->key])) {
                throw new FixtureValidationException(sprintf(
                    'Unknown fixture reference: %s::%s.%s',
                    $reference->fixtureClass,
                    $reference->key,
                    $reference->column,
                ));
            }
        }

        foreach (array_unique($validationProvider->values()) as $name) {
            if (!in_array($name, [
                'identity.userId',
                'identity.username',
                'identity.email',
                'identity.password',
                'identity.apiToken',
            ], true)) {
                throw new FixtureValidationException(sprintf('Unknown runtime fixture value: %s', $name));
            }
        }
    }

    /** @param list<object> $capabilities @param class-string $interface */
    private function assertCount(array $capabilities, string $interface, int $maximum, bool $required, string $directory): void
    {
        $count = count(array_filter($capabilities, static fn (object $item): bool => $item instanceof $interface));

        if (($required && $count !== $maximum) || (!$required && $count > $maximum)) {
            throw new FixtureValidationException(sprintf(
                'Fixture directory must provide %s %s, got %d: %s',
                $required ? 'exactly one' : 'at most one',
                $interface,
                $count,
                $directory,
            ));
        }
    }

    private function validateCapability(
        object $capability,
        ValidationFixtureProvider $validationProvider,
        FixtureProvider $fileProvider,
    ): void {
        if ($capability instanceof ConfigurationFixtureInterface) {
            $this->validateConfiguration($capability->configuration());
        }

        if ($capability instanceof IdentityFixtureInterface) {
            $this->validateIdentity($capability);
        }

        if ($capability instanceof FixtureInterface) {
            $this->validateDatabaseFixtures($capability->getFixturesData($validationProvider));
        }

        if ($capability instanceof RequestFixtureInterface) {
            $this->validateRequest($capability->getRequest($validationProvider), $fileProvider);
        }

        if ($capability instanceof ResponseResultInterface) {
            $this->validateResponse($capability->response($validationProvider));
        }

        if ($capability instanceof SeeInDatabaseInterface) {
            $this->validateDatabaseCriteria($capability->seeInDatabase($validationProvider));
        }

        if ($capability instanceof DontSeeInDatabaseInterface) {
            $this->validateDatabaseCriteria($capability->dontSeeInDatabase($validationProvider));
        }

        if ($capability instanceof CountRecordsInterface) {
            $this->validateDatabaseCounts($capability->countRecords($validationProvider));
        }

        if ($capability instanceof DatabaseStoredFileResultInterface) {
            $this->validateDatabaseStoredFiles($capability->databaseStoredFiles($validationProvider));
        }

        if ($capability instanceof SeeFileInterface) {
            $this->validateFileExpectations($capability->seeFiles($validationProvider));
        }

        if ($capability instanceof DontSeeFileInterface) {
            $this->validateFileExpectations($capability->dontSeeFiles($validationProvider));
        }

        if ($capability instanceof CountFilesInterface) {
            $this->validateFileCountExpectations($capability->countFiles($validationProvider));
        }
    }

    /** @param array<string, mixed> $configuration */
    private function validateConfiguration(array $configuration): void
    {
        $this->assertStringKeys($configuration, 'configuration');
    }

    private function validateIdentity(IdentityFixtureInterface $fixture): void
    {
        foreach ([$fixture->rootPermissions(), $fixture->componentPermissions()] as $permissions) {
            $this->assertStringKeys($permissions, 'identity permissions');

            foreach ($permissions as $permission => $value) {
                if (!is_int($value) || !in_array($value, [-1, 0, 1], true)) {
                    throw new FixtureValidationException(sprintf('Invalid ACL value for %s.', $permission));
                }
            }
        }
    }

    /** @param array<mixed> $fixtures */
    private function validateDatabaseFixtures(array $fixtures): void
    {
        $this->validateDtoList($fixtures, DatabaseFixture::class);
        $keys = [];

        foreach ($fixtures as $fixture) {
            if (!preg_match('/^[A-Za-z0-9_.-]+$/', $fixture->key)) {
                throw new FixtureValidationException(sprintf('Invalid database fixture key: %s', $fixture->key));
            }

            if (isset($keys[$fixture->key])) {
                throw new FixtureValidationException(sprintf('Duplicate database fixture key: %s', $fixture->key));
            }

            if (!preg_match('/^#__[A-Za-z][A-Za-z0-9_]*$/', $fixture->table)) {
                throw new FixtureValidationException(sprintf('Invalid database fixture table: %s', $fixture->table));
            }

            $this->assertColumns($fixture->values);
            $this->assertColumns($fixture->criteria);
            $this->validateValue($fixture->values);
            $this->validateValue($fixture->criteria);
            $this->rejectMutationConditions($fixture->values);
            $this->rejectMutationConditions($fixture->criteria);

            if ($fixture->values === []) {
                throw new FixtureValidationException(sprintf('Database fixture %s has empty values.', $fixture->key));
            }

            if ($fixture->mode !== MutationMode::Insert && $fixture->criteria === []) {
                throw new FixtureValidationException(sprintf('Database fixture %s requires criteria.', $fixture->key));
            }

            $keys[$fixture->key] = true;
        }
    }

    private function validateRequest(RequestData $request, FixtureProvider $fileProvider): void
    {
        $this->validateValue($request->fields);

        if ($request->selection instanceof ListSelection) {
            $this->validateListSelection($request->selection, $request->fields);
        }

        foreach ($request->preparedFiles as $file) {
            if (!$file instanceof FixtureFile) {
                throw new FixtureValidationException('Request preparedFiles must contain only FixtureFile values.');
            }

            $this->validateFixtureFile($file, $fileProvider, true);
        }

        foreach ($request->uploadFiles() as $upload) {
            if (!$upload instanceof UploadFile) {
                throw new FixtureValidationException('Request uploads must contain only UploadFile values.');
            }

            $this->validateUpload($upload, $fileProvider);
        }

        if ($request->content !== null) {
            $this->validateFixtureFile($request->content, $fileProvider, false);
        }
    }

    public function validateUpload(UploadFile $upload, FixtureProvider $fileProvider): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]*(?:\[[A-Za-z0-9_.-]*\])*$/', $upload->inputName)) {
            throw new FixtureValidationException(sprintf('Invalid multipart input name: %s', $upload->inputName));
        }

        $sourcePath = $upload->source === null ? null : $fileProvider->fixturePath($upload->source);

        if ($upload->uploadName !== null && (
            $upload->uploadName === ''
            || str_contains($upload->uploadName, "\n")
            || str_contains($upload->uploadName, "\r")
            || str_contains($upload->uploadName, "\0")
        )) {
            throw new FixtureValidationException('Upload name must be a non-empty value without control separators.');
        }

        if ($upload->clientMime !== null && (
            $upload->clientMime === ''
            || str_contains($upload->clientMime, "\n")
            || str_contains($upload->clientMime, "\r")
        )) {
            throw new FixtureValidationException('Upload client MIME must be a non-empty single-line value.');
        }

        if (!in_array($upload->error, [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
            throw new FixtureValidationException(sprintf(
                'Upload error %d is unsupported by the PhpBrowser transport.',
                $upload->error,
            ));
        }

        $hasSourceConstraint = $upload->sourceMime !== null
            || $upload->sourceMinBytes !== null
            || $upload->sourceMaxBytes !== null;

        if ($hasSourceConstraint && $sourcePath === null) {
            throw new FixtureValidationException('Upload source constraints require a source fixture file.');
        }

        if ($upload->sourceMime !== null) {
            if ($upload->sourceMime === '' || str_contains($upload->sourceMime, "\n") || str_contains($upload->sourceMime, "\r")) {
                throw new FixtureValidationException('Upload source MIME must be a non-empty single-line value.');
            }

            $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $sourcePath);

            if ($actualMime !== $upload->sourceMime) {
                throw new FixtureValidationException(sprintf(
                    'Upload source MIME mismatch: expected %s, got %s.',
                    $upload->sourceMime,
                    is_string($actualMime) ? $actualMime : 'unavailable',
                ));
            }
        }

        foreach (['sourceMinBytes' => $upload->sourceMinBytes, 'sourceMaxBytes' => $upload->sourceMaxBytes] as $name => $bytes) {
            if ($bytes !== null && $bytes < 0) {
                throw new FixtureValidationException(sprintf('Upload %s cannot be negative.', $name));
            }
        }

        if ($upload->sourceMinBytes !== null
            && $upload->sourceMaxBytes !== null
            && $upload->sourceMinBytes > $upload->sourceMaxBytes) {
            throw new FixtureValidationException('Upload sourceMinBytes cannot exceed sourceMaxBytes.');
        }

        if ($sourcePath !== null && ($upload->sourceMinBytes !== null || $upload->sourceMaxBytes !== null)) {
            $sourceBytes = filesize($sourcePath);

            if (!is_int($sourceBytes)) {
                throw new FixtureValidationException(sprintf('Unable to read upload source size: %s', $upload->source));
            }

            if ($upload->sourceMinBytes !== null && $sourceBytes < $upload->sourceMinBytes) {
                throw new FixtureValidationException(sprintf(
                    'Upload source is smaller than sourceMinBytes: %d < %d.',
                    $sourceBytes,
                    $upload->sourceMinBytes,
                ));
            }

            if ($upload->sourceMaxBytes !== null && $sourceBytes > $upload->sourceMaxBytes) {
                throw new FixtureValidationException(sprintf(
                    'Upload source is larger than sourceMaxBytes: %d > %d.',
                    $sourceBytes,
                    $upload->sourceMaxBytes,
                ));
            }
        }
    }

    /** @param array<string, mixed> $fields */
    private function validateListSelection(ListSelection $selection, array $fields): void
    {
        if (array_key_exists('cid', $fields) || array_key_exists('boxchecked', $fields)) {
            throw new FixtureValidationException(
                'Request list selection cannot be combined with manual cid or boxchecked fields.',
            );
        }

        if ($selection->ids === [] || !array_is_list($selection->ids)) {
            throw new FixtureValidationException('Request list selection ids must be a non-empty list.');
        }

        if ($selection->boxchecked !== count($selection->ids)) {
            throw new FixtureValidationException('Request list selection boxchecked must equal the number of ids.');
        }

        foreach ($selection->ids as $id) {
            if ($id instanceof FixtureReference) {
                $this->validateValue($id);

                continue;
            }

            if ((is_int($id) && $id > 0) || (is_string($id) && trim($id) !== '')) {
                continue;
            }

            throw new FixtureValidationException(
                'Request list selection ids must contain positive integers, non-empty strings or fixture references.',
            );
        }
    }

    private function validateResponse(ResponseExpectation $response): void
    {
        foreach ([$response->contains, $response->notContains, $response->uploadedImageUrlNotContains, $response->watchedTables] as $values) {
            foreach ($values as $value) {
                if (!is_string($value)) {
                    throw new FixtureValidationException('Response expectation string lists must contain only strings.');
                }
            }
        }
    }

    /** @param array<mixed> $expectations */
    private function validateDatabaseCriteria(array $expectations): void
    {
        $this->validateDtoList($expectations, DatabaseCriteria::class);

        foreach ($expectations as $expectation) {
            $this->assertDatabaseExpectation($expectation->table, $expectation->criteria, $expectation->timeoutMilliseconds);
        }
    }

    /** @param array<mixed> $expectations */
    private function validateDatabaseCounts(array $expectations): void
    {
        $this->validateDtoList($expectations, DatabaseCountExpectation::class);

        foreach ($expectations as $expectation) {
            $this->assertDatabaseExpectation($expectation->table, $expectation->criteria, $expectation->timeoutMilliseconds);

            if ($expectation->count < 0) {
                throw new FixtureValidationException('Database count expectation cannot be negative.');
            }
        }
    }

    /** @param array<mixed> $expectations */
    private function validateDatabaseStoredFiles(array $expectations): void
    {
        $this->validateDtoList($expectations, DatabaseStoredFileExpectation::class);

        foreach ($expectations as $expectation) {
            $this->assertDatabaseExpectation($expectation->table, $expectation->criteria, 0);

            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $expectation->field)) {
                throw new FixtureValidationException(sprintf('Invalid database stored-file field: %s', $expectation->field));
            }

            if ($expectation->root !== null
                && $expectation->root !== 'public-images'
                && !in_array($expectation->root, ['images', 'storage', 'tmp'], true)) {
                throw new FixtureValidationException(sprintf(
                    'Unknown database stored-file root alias: %s',
                    $expectation->root,
                ));
            }

            if ($expectation->fileExists !== null && $expectation->root === null) {
                throw new FixtureValidationException(
                    'Database stored-file root is required when file existence is asserted.',
                );
            }

            foreach (array_filter([$expectation->jsonPath, ...array_keys($expectation->jsonEquals)]) as $path) {
                if (!is_string($path) || !preg_match('/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/', $path)) {
                    throw new FixtureValidationException(sprintf('Invalid database stored-file JSON path: %s', (string) $path));
                }
            }

            $this->validateValue($expectation->jsonEquals);
        }
    }

    /** @param array<string, mixed> $criteria */
    private function assertDatabaseExpectation(string $table, array $criteria, int $timeoutMilliseconds): void
    {
        if (!preg_match('/^#__[A-Za-z][A-Za-z0-9_]*$/', $table)) {
            throw new FixtureValidationException(sprintf('Invalid database expectation table: %s', $table));
        }

        if ($criteria === []) {
            throw new FixtureValidationException(sprintf('Database expectation for %s requires bounded criteria.', $table));
        }

        $this->assertColumns($criteria);
        $this->validateValue($criteria);

        if ($timeoutMilliseconds < 0) {
            throw new FixtureValidationException('Database expectation timeout cannot be negative.');
        }
    }

    /** @param array<mixed> $expectations */
    private function validateFileExpectations(array $expectations): void
    {
        $this->validateDtoList($expectations, FileExpectation::class);

        foreach ($expectations as $expectation) {
            $this->assertResultRoot($expectation->root);
            $this->assertRelativePath($expectation->relativePath);

            foreach ([$expectation->contentContains, $expectation->contentNotContains] as $values) {
                foreach ($values as $value) {
                    if (!is_string($value)) {
                        throw new FixtureValidationException('File expectation content lists must contain only strings.');
                    }
                }
            }
        }
    }

    /** @param array<mixed> $expectations */
    private function validateFileCountExpectations(array $expectations): void
    {
        $this->validateDtoList($expectations, FileCountExpectation::class);

        foreach ($expectations as $expectation) {
            $this->assertResultRoot($expectation->root);
            $this->assertRelativePath($expectation->relativePath);

            if ($expectation->count < 0) {
                throw new FixtureValidationException('File count expectation cannot be negative.');
            }
        }
    }

    private function validateFixtureFile(FixtureFile $file, FixtureProvider $provider, bool $requiresTarget): void
    {
        $provider->fixturePath($file->source);
        $this->assertRoot($file->root);

        if ($requiresTarget && $file->root !== 'fixture') {
            if ($file->relativePath === null) {
                throw new FixtureValidationException(sprintf('Prepared file %s requires a target path.', $file->source));
            }

            $this->assertRelativePath($file->relativePath);
        }
    }

    /** @param array<mixed> $values @param class-string $class */
    private function validateDtoList(array $values, string $class): void
    {
        if (!array_is_list($values)) {
            throw new FixtureValidationException(sprintf('%s collection must be a list.', $class));
        }

        foreach ($values as $value) {
            if (!$value instanceof $class) {
                throw new FixtureValidationException(sprintf('%s collection contains an incompatible value.', $class));
            }
        }
    }

    /** @param array<mixed> $values */
    private function assertStringKeys(array $values, string $label): void
    {
        foreach (array_keys($values) as $key) {
            if (!is_string($key) || $key === '') {
                throw new FixtureValidationException(sprintf('%s keys must be non-empty strings.', ucfirst($label)));
            }
        }
    }

    /** @param array<string, mixed> $values */
    private function assertColumns(array $values): void
    {
        foreach (array_keys($values) as $column) {
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', (string) $column)) {
                throw new FixtureValidationException(sprintf('Invalid database column: %s', $column));
            }
        }
    }

    private function assertRoot(string $root): void
    {
        if (!in_array($root, ['fixture', 'images', 'storage', 'tmp'], true)) {
            throw new FixtureValidationException(sprintf('Unknown filesystem root alias: %s', $root));
        }
    }

    private function assertResultRoot(string $root): void
    {
        if (!in_array($root, ['images', 'storage', 'tmp'], true)) {
            throw new FixtureValidationException(sprintf('Unknown filesystem result root alias: %s', $root));
        }
    }

    private function assertRelativePath(string $relativePath): void
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');

        if ($relativePath === '' || str_contains($relativePath, "\0") || preg_match('#(^|/)\.\.(/|$)#', $relativePath)) {
            throw new FixtureValidationException(sprintf('Unsafe relative path: %s', $relativePath));
        }
    }

    private function validateValue(mixed $value): void
    {
        if ($value instanceof FixtureReference) {
            if (
                $value->fixtureClass === ''
                || !preg_match('/^[A-Za-z0-9_.-]+$/', $value->key)
                || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value->column)
            ) {
                throw new FixtureValidationException('Invalid fixture reference.');
            }

            $this->encounteredReferences[] = $value;

            return;
        }

        if ($value instanceof DatabaseCondition) {
            $this->validateValue($value->value);

            return;
        }

        if (is_object($value)) {
            throw new FixtureValidationException(sprintf('Unsupported fixture value object: %s', $value::class));
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            $this->validateValue($item);
        }
    }

    private function rejectMutationConditions(mixed $value): void
    {
        if ($value instanceof DatabaseCondition) {
            throw new FixtureValidationException(
                'DatabaseCondition is supported only by database result criteria, not by mutation fixtures.',
            );
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            $this->rejectMutationConditions($item);
        }
    }
}
