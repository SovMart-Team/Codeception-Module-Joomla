<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Contract\AuthenticationAdapterInterface;
use JoomlaCodeception\Contract\CsrfTokenProviderInterface;
use JoomlaCodeception\Dto\JoomlaClient;
use JoomlaCodeception\Dto\ModuleConfiguration;

final class JoomlaSessionManager
{
    private ?string $csrfToken = null;

    private ?int $userId = null;

    public function __construct(
        private readonly PhpBrowser $browser,
        private readonly \PDO $database,
        private readonly DatabaseFixtureManager $fixtures,
        private readonly AuthenticationAdapterInterface $authentication,
        private readonly CsrfTokenProviderInterface $csrfTokens,
        private readonly ModuleConfiguration $configuration,
    ) {
    }

    /** @param array<string, mixed> $identity */
    public function login(JoomlaClient $client, array $identity): void
    {
        $this->userId = (int) ($identity['userId'] ?? 0);
        $this->authentication->login($this->browser, $client, $identity, $this->configuration);
        $this->csrfToken = null;
    }

    public function csrfToken(JoomlaClient $client): string
    {
        return $this->csrfToken ??= $this->csrfTokens->token(
            $this->browser,
            $this->database,
            $this->fixtures,
            $client,
            $this->userId,
            $this->configuration,
        );
    }
}
