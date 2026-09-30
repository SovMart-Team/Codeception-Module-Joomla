<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use JoomlaCodeception\Context\FixtureCapabilityLoader;
use JoomlaCodeception\Context\FixtureContractValidator;
use JoomlaCodeception\Contract\DatabaseStoredFileResultInterface;
use JoomlaCodeception\Contract\FixtureInterface;
use JoomlaCodeception\Contract\FixtureProviderInterface;
use JoomlaCodeception\Contract\RequestFixtureInterface;
use JoomlaCodeception\Contract\ResponseResultInterface;
use JoomlaCodeception\Contract\SeeFileInterface;
use JoomlaCodeception\Dto\DatabaseCondition;
use JoomlaCodeception\Dto\DatabaseFixture;
use JoomlaCodeception\Dto\DatabaseOperator;
use JoomlaCodeception\Dto\DatabaseStoredFileExpectation;
use JoomlaCodeception\Dto\FileExpectation;
use JoomlaCodeception\Dto\FixtureReference;
use JoomlaCodeception\Dto\ListSelection;
use JoomlaCodeception\Dto\RequestData;
use JoomlaCodeception\Dto\ResponseExpectation;
use JoomlaCodeception\Dto\UploadFile;
use JoomlaCodeception\Exception\FixtureValidationException;
use JoomlaCodeception\Service\FixtureProvider;
use PHPUnit\Framework\TestCase;

final class FixtureContractValidatorTest extends TestCase
{
    public function testAcceptsRuntimeUploadFromNamedAssetRoot(): void
    {
        $provider = new FixtureProvider(__DIR__, ['security' => __DIR__]);

        (new FixtureContractValidator())->validateUpload(
            new UploadFile('@security/SupportAutoload.php', 'jform[file]', 'payload.php.jpg', 'image/jpeg'),
            $provider
        );

        self::addToAssertionCount(1);
    }

    public function testRejectsControlSeparatorInRuntimeUploadName(): void
    {
        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('Upload name');

        (new FixtureContractValidator())->validateUpload(
            new UploadFile('SupportAutoload.php', uploadName: "payload.jpg\r\nX-Test: injected"),
            new FixtureProvider(__DIR__)
        );
    }

    public function testAcceptsCompleteMethodCapabilities(): void
    {
        [$directory, $capabilities] = $this->capabilities();

        (new FixtureContractValidator())->validate($capabilities, $directory);
        self::addToAssertionCount(1);
    }

    public function testRejectsDuplicateSingletonCapabilityBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $request                    = array_values(array_filter($capabilities, static fn (object $item): bool => $item instanceof RequestFixtureInterface))[0];
        $capabilities[]             = $request;

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('exactly one');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testRejectsCapabilityWithMultipleContracts(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities[]             = new MultipleContractsFixture();

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('exactly one phase');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testRejectsInvalidDtoListBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities[]             = new InvalidDatabaseFixture();

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('incompatible value');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testRejectsManuallyConstructedUnknownFixtureReference(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $fixture                    = array_values(array_filter($capabilities, static fn (object $item): bool => $item instanceof FixtureInterface))[0];
        $capabilities               = $this->replaceRequest($capabilities, new UnknownReferenceRequestFixture($fixture::class));

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('Unknown fixture reference');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testRejectsUnknownRuntimeValue(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities               = $this->replaceRequest($capabilities, new UnknownRuntimeValueRequestFixture());

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('Unknown runtime fixture value');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testAcceptsApiTokenRuntimeValue(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities               = $this->replaceRequest($capabilities, new ApiTokenRuntimeValueRequestFixture());

        (new FixtureContractValidator())->validate($capabilities, $directory);
        self::addToAssertionCount(1);
    }

    public function testRejectsUnsupportedUploadErrorBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities               = $this->replaceRequest($capabilities, new UnsupportedUploadErrorRequestFixture());

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('unsupported by the PhpBrowser transport');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testRejectsMultilineUploadMimeBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities               = $this->replaceRequest($capabilities, new MultilineMimeRequestFixture());

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('single-line');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testAcceptsValidUploadSourceConstraintsBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities               = $this->replaceRequest($capabilities, new ConstrainedUploadRequestFixture());

        (new FixtureContractValidator())->validate($capabilities, $directory);
        self::addToAssertionCount(1);
    }

    public function testRejectsUploadSourceConstraintsWithoutSourceBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities               = $this->replaceRequest($capabilities, new MissingSourceConstrainedUploadRequestFixture());

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('require a source fixture file');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testRejectsUploadSourceMimeMismatchBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities               = $this->replaceRequest($capabilities, new MismatchedSourceMimeUploadRequestFixture());

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('source MIME mismatch');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testRejectsUploadSourceOutsideSizeRangeBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities               = $this->replaceRequest($capabilities, new OversizedSourceUploadRequestFixture());

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('larger than sourceMaxBytes');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testAcceptsDatabaseStoredFileResultContract(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities[]             = new ValidDatabaseStoredFileResult();

        (new FixtureContractValidator())->validate($capabilities, $directory);
        self::addToAssertionCount(1);
    }

    public function testRejectsDatabaseStoredFileWithoutRootForExistenceAssertion(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities[]             = new MissingRootDatabaseStoredFileResult();

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('root is required');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testRejectsDatabaseConditionInMutationFixtureBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities[]             = new MutationConditionFixture();

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('supported only by database result criteria');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testRejectsFixtureAliasAsFileResultRootBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities[]             = new FixtureRootResult();

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('filesystem result root');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testRejectsEmptyListSelectionBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities               = $this->replaceRequest($capabilities, new EmptyListSelectionRequestFixture());

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('ids must be a non-empty list');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testRejectsListSelectionConflictingWithManualFieldsBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities               = $this->replaceRequest($capabilities, new ConflictingListSelectionRequestFixture());

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('cannot be combined with manual cid or boxchecked');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    public function testRejectsMismatchedListSelectionBoxcheckedBeforeMutation(): void
    {
        [$directory, $capabilities] = $this->capabilities();
        $capabilities               = $this->replaceRequest($capabilities, new MismatchedListSelectionRequestFixture());

        $this->expectException(FixtureValidationException::class);
        $this->expectExceptionMessage('boxchecked must equal the number of ids');
        (new FixtureContractValidator())->validate($capabilities, $directory);
    }

    /** @param list<object> $capabilities @return list<object> */
    private function replaceRequest(array $capabilities, RequestFixtureInterface $replacement): array
    {
        return array_map(
            static fn (object $item): object => $item instanceof RequestFixtureInterface ? $replacement : $item,
            $capabilities,
        );
    }

    /** @return array{string, list<object>} */
    private function capabilities(): array
    {
        $fixtureRoot  = dirname(__DIR__) . '/Fixtures/Functional';
        $directory    = $fixtureRoot . '/Administrator/Controller/PortableController/exampleMethod';
        $capabilities = (new FixtureCapabilityLoader(
            $fixtureRoot,
            'JoomlaCodeception\\Tests\\Fixtures\\Functional',
        ))->load($directory);

        return [$directory, $capabilities];
    }
}

final readonly class MultipleContractsFixture implements RequestFixtureInterface, ResponseResultInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData();
    }

    public function response(FixtureProviderInterface $fixtureProvider): ResponseExpectation
    {
        return new ResponseExpectation();
    }
}

final readonly class InvalidDatabaseFixture implements FixtureInterface
{
    public function getFixturesData(FixtureProviderInterface $fixtureProvider): array
    {
        return ['invalid'];
    }
}

final readonly class UnknownReferenceRequestFixture implements RequestFixtureInterface
{
    /** @param class-string $fixtureClass */
    public function __construct(private string $fixtureClass)
    {
    }

    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(fields: [
            'id' => new FixtureReference($this->fixtureClass, 'unknown-key'),
        ]);
    }
}

final readonly class UnknownRuntimeValueRequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(fields: ['value' => $fixtureProvider->value('unknown.value')]);
    }
}

final readonly class ApiTokenRuntimeValueRequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(fields: ['token' => $fixtureProvider->value('identity.apiToken')]);
    }
}

final readonly class UnsupportedUploadErrorRequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(upload: new UploadFile(null, error: UPLOAD_ERR_PARTIAL));
    }
}

final readonly class MultilineMimeRequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(upload: new UploadFile(null, clientMime: "image/png\r\nX-Test: injected"));
    }
}

final readonly class ConstrainedUploadRequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(upload: new UploadFile(
            'DatabaseFixture.php',
            sourceMinBytes: 1,
            sourceMaxBytes: PHP_INT_MAX,
        ));
    }
}

final readonly class MissingSourceConstrainedUploadRequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(upload: new UploadFile(null, sourceMinBytes: 1));
    }
}

final readonly class MismatchedSourceMimeUploadRequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(upload: new UploadFile(
            'DatabaseFixture.php',
            sourceMime: 'application/x-impossible-mime',
        ));
    }
}

final readonly class OversizedSourceUploadRequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(upload: new UploadFile('DatabaseFixture.php', sourceMaxBytes: 1));
    }
}

final readonly class ValidDatabaseStoredFileResult implements DatabaseStoredFileResultInterface
{
    public function databaseStoredFiles(FixtureProviderInterface $fixtureProvider): array
    {
        return [new DatabaseStoredFileExpectation(
            table: '#__records',
            field: 'path',
            criteria: ['id' => 1],
            root: 'storage',
            valueSuffix: '.zip',
            fileExists: true,
        )];
    }
}

final readonly class MissingRootDatabaseStoredFileResult implements DatabaseStoredFileResultInterface
{
    public function databaseStoredFiles(FixtureProviderInterface $fixtureProvider): array
    {
        return [new DatabaseStoredFileExpectation(
            table: '#__records',
            field: 'path',
            criteria: ['id' => 1],
            fileExists: true,
        )];
    }
}

final readonly class MutationConditionFixture implements FixtureInterface
{
    public function getFixturesData(FixtureProviderInterface $fixtureProvider): array
    {
        return [
            new DatabaseFixture(
                'condition',
                '#__example',
                ['state' => new DatabaseCondition(DatabaseOperator::Equal, 1)],
            ),
        ];
    }
}

final readonly class FixtureRootResult implements SeeFileInterface
{
    public function seeFiles(FixtureProviderInterface $fixtureProvider): array
    {
        return [new FileExpectation('fixture', 'response.json')];
    }
}

final readonly class EmptyListSelectionRequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(selection: new ListSelection([]));
    }
}

final readonly class ConflictingListSelectionRequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(fields: ['cid' => [1]], selection: new ListSelection([1]));
    }
}

final readonly class MismatchedListSelectionRequestFixture implements RequestFixtureInterface
{
    public function getRequest(FixtureProviderInterface $fixtureProvider): RequestData
    {
        return new RequestData(selection: new ListSelection([1, 2], boxchecked: 1));
    }
}
