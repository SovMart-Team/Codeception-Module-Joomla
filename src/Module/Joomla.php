<?php

declare(strict_types=1);

namespace JoomlaCodeception\Module;

use Codeception\Module;
use Codeception\Module\Db;
use Codeception\Module\PhpBrowser;
use Codeception\Test\Cest;
use Codeception\TestInterface;
use JoomlaCodeception\Context\CurrentTestContext;
use JoomlaCodeception\Context\FixtureCapabilityLoader;
use JoomlaCodeception\Context\FixtureContractValidator;
use JoomlaCodeception\Context\FixtureDirectoryLocator;
use JoomlaCodeception\Context\JoomlaTestDiagnostics;
use JoomlaCodeception\Contract\AuthenticationAdapterInterface;
use JoomlaCodeception\Contract\BrowserTransportInterface;
use JoomlaCodeception\Contract\ConfigurationFixtureInterface;
use JoomlaCodeception\Contract\CsrfTokenProviderInterface;
use JoomlaCodeception\Contract\DatabaseConnectionProviderInterface;
use JoomlaCodeception\Contract\IdentityFixtureInterface;
use JoomlaCodeception\Dto\JoomlaClient;
use JoomlaCodeception\Dto\ModuleConfiguration;
use JoomlaCodeception\Dto\UploadFile;
use JoomlaCodeception\Exception\JoomlaTestException;
use JoomlaCodeception\Service\CleanupJournal;
use JoomlaCodeception\Service\CodeceptionDbConnectionProvider;
use JoomlaCodeception\Service\ConfigurationFixtureManager;
use JoomlaCodeception\Service\CsrfTokenManager;
use JoomlaCodeception\Service\DatabaseAssertionManager;
use JoomlaCodeception\Service\DatabaseConditionBuilder;
use JoomlaCodeception\Service\DatabaseFixtureManager;
use JoomlaCodeception\Service\DatabaseStoredFileAssertionManager;
use JoomlaCodeception\Service\FileFixtureManager;
use JoomlaCodeception\Service\FilesystemAssertionManager;
use JoomlaCodeception\Service\FixtureProvider;
use JoomlaCodeception\Service\FormInspector;
use JoomlaCodeception\Service\FormSubmitter;
use JoomlaCodeception\Service\HttpRequestDispatcher;
use JoomlaCodeception\Service\IdentityManager;
use JoomlaCodeception\Service\JoomlaSessionManager;
use JoomlaCodeception\Service\MariaDbAdvisoryLock;
use JoomlaCodeception\Service\MediaRequestBuilder;
use JoomlaCodeception\Service\MockRouteManager;
use JoomlaCodeception\Service\MultipartRequestBuilder;
use JoomlaCodeception\Service\PhpBrowserTransport;
use JoomlaCodeception\Service\ResponseAssertionManager;
use JoomlaCodeception\Service\RouteHelper;
use JoomlaCodeception\Service\SideEffectSnapshotManager;

final class Joomla extends Module
{
    protected array $config = [
        'client'                  => '',
        'projectRoot'             => '',
        'fixtureRoot'             => '',
        'fixtureNamespace'        => '',
        'component'               => '',
        'componentAsset'          => '',
        'databasePrefix'          => '',
        'administratorLoginRoute' => '/administrator/index.php',
        'administratorTokenRoute' => '/administrator/index.php',
        'siteLoginRoute'          => '/index.php?option=com_users&view=login',
        'siteTokenRoute'          => '/',
        'siteLoginSelector'       => 'form#login-form',
        'siteUsernameField'       => 'username',
        'sitePasswordField'       => 'password',
        'fixtureAssetRoots'       => [],
        'filesystemRoots'         => [],
        'publicImagePrefix'       => '/images',
        'mockBaseUrl'             => '',
        'authenticationAdapter'   => 'JoomlaCodeception\\Service\\DefaultAuthenticationAdapter',
        'csrfTokenProvider'       => 'JoomlaCodeception\\Service\\HtmlCsrfTokenProvider',
        'apiParentGroupId'        => 8,
        'mutationLockName'        => 'joomla-codeception-fixtures',
        'mutationLockTimeout'     => 30,
    ];

    protected array $requiredFields = [
        'client',
        'projectRoot',
        'fixtureRoot',
        'fixtureNamespace',
        'component',
        'componentAsset',
        'databasePrefix',
        'filesystemRoots',
    ];

    private ?Cest $currentTest = null;

    private ?JoomlaClient $client = null;

    private ?CurrentTestContext $currentContext = null;

    private ?CleanupJournal $cleanupJournal = null;

    private ?ConfigurationFixtureManager $configuration = null;

    private ?DatabaseFixtureManager $databaseFixtures = null;

    private ?IdentityManager $identities = null;

    private ?JoomlaSessionManager $session = null;

    private ?FileFixtureManager $files = null;

    private ?HttpRequestDispatcher $requests = null;

    private ?ResponseAssertionManager $responses = null;

    private ?DatabaseAssertionManager $databaseAssertions = null;

    private ?DatabaseStoredFileAssertionManager $databaseStoredFileAssertions = null;

    private ?FilesystemAssertionManager $filesystemAssertions = null;

    private ?SideEffectSnapshotManager $snapshots = null;

    private ?ModuleConfiguration $moduleConfiguration = null;

    private ?MariaDbAdvisoryLock $mutationLock = null;

    private ?BrowserTransportInterface $transport = null;

    private ?DatabaseConnectionProviderInterface $databaseConnection = null;

    private JoomlaTestDiagnostics $diagnostics;

    public function _initialize(): void
    {
        $this->diagnostics = new JoomlaTestDiagnostics();
    }

    public function _before(TestInterface $test): void
    {
        $this->currentTest = $test instanceof Cest ? $test : null;
        $this->resetState(false);
    }

    public function _failed(TestInterface $test, \Exception $fail): void
    {
        try {
            $this->runCleanup();
        } catch (\Throwable $cleanupFailure) {
            $this->debugSection('Joomla cleanup', $this->diagnostics->redact($cleanupFailure->getMessage()));
        }
    }

    public function _after(TestInterface $test): void
    {
        try {
            $this->runCleanup();
        } finally {
            $this->resetState(true);
        }
    }

    public function applyConfigurationFixtures(): void
    {
        $this->perform(__FUNCTION__, function (CurrentTestContext $context): void {
            if ($context->configurationApplied) {
                return;
            }

            $fixture = $context->find(ConfigurationFixtureInterface::class);

            if ($fixture instanceof ConfigurationFixtureInterface) {
                $this->configuration()->apply($fixture->configuration());
            }

            $context->configurationApplied = true;
        });
    }

    public function loadDbFixtures(): void
    {
        $this->perform(__FUNCTION__, function (CurrentTestContext $context): void {
            if ($context->databaseLoaded) {
                return;
            }

            $this->resolveIdentity($context);
            $this->databaseFixtures()->apply($context->capabilities, $context->provider);
            $context->databaseLoaded = true;
        });
    }

    public function loadFileFixtures(): void
    {
        $this->perform(__FUNCTION__, function (CurrentTestContext $context): void {
            if ($context->filesLoaded) {
                return;
            }

            $this->files()->materialize($context->requestData()->preparedFiles, $context->provider);
            $context->filesLoaded = true;
        });
    }

    public function loginFromFixture(): void
    {
        $this->perform(__FUNCTION__, function (CurrentTestContext $context): void {
            if ($context->authenticated) {
                return;
            }

            $this->resolveIdentity($context);

            if ($context->identity === []) {
                throw new \RuntimeException(sprintf('IdentityFixture is missing in %s.', $context->directory));
            }

            $this->session()->login($this->client(), $context->identity);
            $context->authenticated = true;
        });
    }

    public function openPageFromFixture(string $route): void
    {
        $this->requestAction(__FUNCTION__, fn (CurrentTestContext $context) => $this->requests()->openPage(
            $route,
            $context->requestData(),
            $context->provider,
            $this->client(),
            $context->authenticated,
            $context->controller,
            $context->method,
        ));
    }

    public function sendAjaxPostFromFixture(string $route): void
    {
        $this->requestAction(__FUNCTION__, fn (CurrentTestContext $context) => $this->requests()->sendAjaxPost(
            $route,
            $context->requestData(),
            $context->provider,
            $this->client(),
            $context->authenticated,
            $context->controller,
            $context->method,
        ));
    }

    public function sendJsonFromFixture(string $route): void
    {
        $this->requestAction(__FUNCTION__, fn (CurrentTestContext $context) => $this->requests()->sendJson(
            $route,
            $context->requestData(),
            $context->provider,
            $this->client(),
            $context->authenticated,
            $context->controller,
            $context->method,
        ));
    }

    public function sendRequestFromFixture(string $method, string $route): void
    {
        $this->requestAction(__FUNCTION__, fn (CurrentTestContext $context) => $this->requests()->sendRequest(
            $method,
            $route,
            $context->requestData(),
            $context->provider,
            $this->client(),
            $context->authenticated,
            $context->controller,
            $context->method,
        ));
    }

    public function submitFormFromFixture(string $page, string $selector = 'form'): void
    {
        $this->requestAction(__FUNCTION__, fn (CurrentTestContext $context) => $this->requests()->submitForm(
            $page,
            $selector,
            $context->requestData(),
            $context->provider,
            $this->client(),
            $context->authenticated,
            $context->controller,
            $context->method,
        ));
    }

    public function submitMultipartFromFixture(string $route): void
    {
        $this->requestAction(__FUNCTION__, fn (CurrentTestContext $context) => $this->requests()->submitMultipart(
            $route,
            $context->requestData(),
            $context->provider,
            $this->client(),
            $context->authenticated,
            $context->controller,
            $context->method,
        ));
    }

    public function submitMultipartFromFixtureWithUpload(string $route, UploadFile $upload): void
    {
        $this->requestAction(__FUNCTION__, function (CurrentTestContext $context) use ($route, $upload): void {
            (new FixtureContractValidator())->validateUpload($upload, $context->provider);
            $this->requests()->submitMultipart(
                $route,
                $context->requestData()->withUpload($upload),
                $context->provider,
                $this->client(),
                $context->authenticated,
                $context->controller,
                $context->method,
            );
        });
    }

    public function sendMediaFromFixture(string $route): void
    {
        $this->requestAction(__FUNCTION__, fn (CurrentTestContext $context) => $this->requests()->sendMedia(
            $route,
            $context->requestData(),
            $context->provider,
            $this->client(),
            $context->authenticated,
            $context->controller,
            $context->method,
        ));
    }

    public function checkResponseResults(): void
    {
        $this->perform(__FUNCTION__, fn (CurrentTestContext $context) => $this->responses()->assert($context->responseExpectation()));
    }

    public function checkDbResults(): void
    {
        $this->perform(__FUNCTION__, fn (CurrentTestContext $context) => $this->databaseAssertions()->assert($context->capabilities, $context->provider));
    }

    public function checkFileResults(): void
    {
        $this->perform(__FUNCTION__, function (CurrentTestContext $context): void {
            $this->filesystemAssertions()->assert($context->capabilities, $context->provider);
            $this->databaseStoredFileAssertions()->assert($context->capabilities, $context->provider);
        });
    }

    public function checkSideEffectResults(): void
    {
        $this->perform(__FUNCTION__, function (CurrentTestContext $context): void {
            $expectation = $context->responseExpectation();

            if (!$expectation->noSideEffects) {
                return;
            }

            if ($context->sideEffectSnapshot === null) {
                throw new \RuntimeException('Side-effect snapshot is missing; send the request before checking results.');
            }

            $this->snapshots()->assertUnchanged($context->sideEffectSnapshot, $expectation->watchedTables);
        });
    }

    public function getRawFixture(string $relativePath): string
    {
        return $this->perform(__FUNCTION__, function (CurrentTestContext $context) use ($relativePath): string {
            $content = file_get_contents($context->provider->fixturePath($relativePath));

            if (!is_string($content)) {
                throw new \RuntimeException(sprintf('Unable to read raw fixture: %s', $relativePath));
            }

            return $content;
        });
    }

    /** @return array<string, mixed> */
    public function getJsonFixture(string $relativePath): array
    {
        $decoded = json_decode($this->getRawFixture($relativePath), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('JSON fixture must decode to an array: %s', $relativePath));
        }

        return $decoded;
    }

    private function requestAction(string $action, callable $request): void
    {
        $this->perform($action, function (CurrentTestContext $context) use ($request): void {
            $expectation      = $context->responseExpectation();
            $databaseBaseline = $this->databaseFixtures()->captureBaseline($expectation->watchedTables);
            $this->journal()->register(
                'database request baseline',
                fn () => $this->databaseFixtures()->removeCreatedRows($databaseBaseline),
            );
            $context->sideEffectSnapshot = $this->snapshots()->capture($expectation->watchedTables);
            $request($context);
        });
    }

    private function resolveIdentity(CurrentTestContext $context): void
    {
        if ($context->identityResolved) {
            return;
        }

        $fixture           = $context->find(IdentityFixtureInterface::class);
        $context->identity = $fixture instanceof IdentityFixtureInterface
            ? $this->identities()->create($this->client(), $fixture)
            : [];
        $context->provider->setValue('identity.userId', (int) ($context->identity['userId'] ?? 0));
        $context->provider->setValue('identity.username', (string) ($context->identity['username'] ?? ''));
        $context->provider->setValue('identity.email', (string) ($context->identity['email'] ?? ''));
        $context->provider->setValue('identity.password', (string) ($context->identity['password'] ?? ''));
        $context->provider->setValue('identity.apiToken', (string) ($context->identity['apiToken'] ?? ''));
        $context->identityResolved = true;
    }

    /** @template T @param callable(CurrentTestContext): T $operation @return T */
    private function perform(string $action, callable $operation): mixed
    {
        try {
            return $operation($this->context());
        } catch (\Throwable $primary) {
            $context        = $this->currentContext;
            $cleanupMessage = '';

            try {
                $this->runCleanup();
            } catch (\Throwable $cleanupFailure) {
                $cleanupMessage = '; cleanup: ' . $this->diagnostics->redact($cleanupFailure->getMessage());
            }

            throw new JoomlaTestException(
                $this->diagnostics->failure(
                    $action,
                    $context?->cest ?? $this->currentCestName(),
                    $context?->method ?? ($this->currentTest?->getTestMethod() ?? 'unknown'),
                    $context?->directory ?? 'unresolved',
                    $primary,
                ) . $cleanupMessage,
                0,
                $primary,
            );
        }
    }

    private function context(): CurrentTestContext
    {
        if ($this->currentContext !== null) {
            return $this->currentContext;
        }

        if (!$this->currentTest instanceof Cest) {
            throw new \RuntimeException('Joomla fixture actions can only be used from a Cest method.');
        }

        $configuration = $this->moduleConfiguration();
        $client        = $this->client();
        $cest          = $this->currentTest->getTestInstance();
        $method        = $this->currentTest->getTestMethod();
        $directory     = (new FixtureDirectoryLocator($configuration->fixtureRoot))->locate($client, $cest, $method);
        $capabilities  = (new FixtureCapabilityLoader(
            $configuration->fixtureRoot,
            $configuration->fixtureNamespace,
        ))->load($directory);
        (new FixtureContractValidator())->validate($capabilities, $directory, $configuration->fixtureAssetRoots);
        $cestName = (new \ReflectionClass($cest))->getShortName();
        $context  = new CurrentTestContext(
            $cestName,
            substr($cestName, 0, -4),
            $method,
            $directory,
            $capabilities,
            new FixtureProvider($directory, $configuration->fixtureAssetRoots),
        );
        $this->mutationLock()->acquire();
        $baseline = $this->files()->captureBaseline();
        $this->journal()->register('filesystem baseline', fn () => $this->files()->restoreBaseline($baseline));
        $this->currentContext = $context;

        return $context;
    }

    private function client(): JoomlaClient
    {
        if ($this->client === null) {
            $this->client = JoomlaClient::tryFrom((string) $this->config['client'])
                ?? throw new \RuntimeException(sprintf('Unsupported Joomla module client: %s', $this->config['client']));
        }

        return $this->client;
    }

    private function journal(): CleanupJournal
    {
        return $this->cleanupJournal ??= new CleanupJournal();
    }

    private function runCleanup(): void
    {
        if ($this->cleanupJournal !== null && !$this->cleanupJournal->isEmpty()) {
            $this->cleanupJournal->cleanup();
        }
    }

    private function databaseFixtures(): DatabaseFixtureManager
    {
        if ($this->databaseFixtures === null) {
            $this->databaseFixtures = new DatabaseFixtureManager(
                $this->databaseConnection()->connection(),
                $this->databasePrefix(),
                $this->journal(),
            );
        }

        return $this->databaseFixtures;
    }

    private function configuration(): ConfigurationFixtureManager
    {
        return $this->configuration ??= new ConfigurationFixtureManager(
            $this->databaseConnection()->connection(),
            $this->databaseFixtures(),
            $this->journal(),
            $this->moduleConfiguration()->component,
        );
    }

    private function identities(): IdentityManager
    {
        return $this->identities ??= new IdentityManager(
            $this->databaseConnection()->connection(),
            $this->databaseFixtures(),
            $this->journal(),
            $this->moduleConfiguration()->componentAsset,
            $this->moduleConfiguration()->projectRoot,
            $this->moduleConfiguration()->apiParentGroupId,
        );
    }

    private function session(): JoomlaSessionManager
    {
        return $this->session ??= new JoomlaSessionManager(
            $this->browser(),
            $this->databaseConnection()->connection(),
            $this->databaseFixtures(),
            $this->authenticationAdapter(),
            $this->csrfTokenProvider(),
            $this->moduleConfiguration(),
        );
    }

    private function files(): FileFixtureManager
    {
        $configuration = $this->moduleConfiguration();

        return $this->files ??= new FileFixtureManager(
            $configuration->filesystemRoots,
            $configuration->publicImagePrefix,
            $this->journal(),
        );
    }

    private function requests(): HttpRequestDispatcher
    {
        return $this->requests ??= new HttpRequestDispatcher(
            $this->browser(),
            $this->session(),
            new CsrfTokenManager($this->session()),
            new RouteHelper(),
            new FormSubmitter(
                $this->browser(),
                new FormInspector($this->browser()),
                $this->transport(),
                new RouteHelper(),
            ),
            new MultipartRequestBuilder(),
            new MediaRequestBuilder(),
            new MockRouteManager($this->moduleConfiguration()->mockBaseUrl),
            $this->transport(),
        );
    }

    private function responses(): ResponseAssertionManager
    {
        return $this->responses ??= new ResponseAssertionManager($this->browser(), $this->files(), $this->transport());
    }

    private function databaseAssertions(): DatabaseAssertionManager
    {
        return $this->databaseAssertions ??= new DatabaseAssertionManager(
            $this->databaseConnection()->connection(),
            $this->databaseFixtures(),
            new DatabaseConditionBuilder(),
        );
    }

    private function filesystemAssertions(): FilesystemAssertionManager
    {
        return $this->filesystemAssertions ??= new FilesystemAssertionManager($this->files());
    }

    private function databaseStoredFileAssertions(): DatabaseStoredFileAssertionManager
    {
        return $this->databaseStoredFileAssertions ??= new DatabaseStoredFileAssertionManager(
            $this->databaseConnection()->connection(),
            $this->databaseFixtures(),
            new DatabaseConditionBuilder(),
            $this->files(),
        );
    }

    private function snapshots(): SideEffectSnapshotManager
    {
        return $this->snapshots ??= new SideEffectSnapshotManager($this->databaseFixtures(), $this->files());
    }

    private function browser(): PhpBrowser
    {
        $module = $this->getModule('PhpBrowser');

        if (!$module instanceof PhpBrowser) {
            throw new \RuntimeException('Joomla module requires PhpBrowser.');
        }

        return $module;
    }

    private function databaseModule(): Db
    {
        $module = $this->getModule('Db');

        if (!$module instanceof Db) {
            throw new \RuntimeException('Joomla module requires Db.');
        }

        return $module;
    }

    private function currentCestName(): string
    {
        if (!$this->currentTest instanceof Cest) {
            return 'unknown';
        }

        return (new \ReflectionClass($this->currentTest->getTestInstance()))->getShortName();
    }

    private function resetState(bool $clearTest): void
    {
        $this->client                       = null;
        $this->currentContext               = null;
        $this->cleanupJournal               = null;
        $this->configuration                = null;
        $this->databaseFixtures             = null;
        $this->identities                   = null;
        $this->session                      = null;
        $this->files                        = null;
        $this->requests                     = null;
        $this->responses                    = null;
        $this->databaseAssertions           = null;
        $this->databaseStoredFileAssertions = null;
        $this->filesystemAssertions         = null;
        $this->snapshots                    = null;
        $this->moduleConfiguration          = null;
        $this->mutationLock                 = null;
        $this->transport                    = null;
        $this->databaseConnection           = null;

        if ($clearTest) {
            $this->currentTest = null;
        }
    }

    private function moduleConfiguration(): ModuleConfiguration
    {
        return $this->moduleConfiguration ??= new ModuleConfiguration(
            projectRoot: rtrim((string) $this->config['projectRoot'], '/'),
            fixtureRoot: rtrim((string) $this->config['fixtureRoot'], '/'),
            fixtureNamespace: trim((string) $this->config['fixtureNamespace'], '\\'),
            component: (string) $this->config['component'],
            componentAsset: (string) $this->config['componentAsset'],
            databasePrefix: (string) $this->config['databasePrefix'],
            administratorLoginRoute: (string) $this->config['administratorLoginRoute'],
            administratorTokenRoute: (string) $this->config['administratorTokenRoute'],
            siteLoginRoute: (string) $this->config['siteLoginRoute'],
            siteTokenRoute: (string) $this->config['siteTokenRoute'],
            siteLoginSelector: (string) $this->config['siteLoginSelector'],
            siteUsernameField: (string) $this->config['siteUsernameField'],
            sitePasswordField: (string) $this->config['sitePasswordField'],
            fixtureAssetRoots: is_array($this->config['fixtureAssetRoots']) ? $this->config['fixtureAssetRoots'] : [],
            filesystemRoots: is_array($this->config['filesystemRoots']) ? $this->config['filesystemRoots'] : [],
            publicImagePrefix: (string) $this->config['publicImagePrefix'],
            mockBaseUrl: (string) $this->config['mockBaseUrl'],
            authenticationAdapter: (string) $this->config['authenticationAdapter'],
            csrfTokenProvider: (string) $this->config['csrfTokenProvider'],
            apiParentGroupId: (int) $this->config['apiParentGroupId'],
            mutationLockName: (string) $this->config['mutationLockName'],
            mutationLockTimeout: (int) $this->config['mutationLockTimeout'],
        );
    }

    private function authenticationAdapter(): AuthenticationAdapterInterface
    {
        $class   = $this->moduleConfiguration()->authenticationAdapter;
        $adapter = new $class();

        if (!$adapter instanceof AuthenticationAdapterInterface) {
            throw new \RuntimeException(sprintf('%s must implement %s.', $class, AuthenticationAdapterInterface::class));
        }

        return $adapter;
    }

    private function csrfTokenProvider(): CsrfTokenProviderInterface
    {
        $class    = $this->moduleConfiguration()->csrfTokenProvider;
        $provider = new $class();

        if (!$provider instanceof CsrfTokenProviderInterface) {
            throw new \RuntimeException(sprintf('%s must implement %s.', $class, CsrfTokenProviderInterface::class));
        }

        return $provider;
    }

    private function mutationLock(): MariaDbAdvisoryLock
    {
        $configuration = $this->moduleConfiguration();

        return $this->mutationLock ??= new MariaDbAdvisoryLock(
            $this->databaseConnection()->connection(),
            $this->journal(),
            $configuration->mutationLockName,
            $configuration->mutationLockTimeout,
        );
    }

    private function transport(): BrowserTransportInterface
    {
        return $this->transport ??= new PhpBrowserTransport($this->browser());
    }

    private function databaseConnection(): DatabaseConnectionProviderInterface
    {
        return $this->databaseConnection ??= new CodeceptionDbConnectionProvider($this->databaseModule());
    }

    private function databasePrefix(): string
    {
        return $this->moduleConfiguration()->databasePrefix;
    }
}
