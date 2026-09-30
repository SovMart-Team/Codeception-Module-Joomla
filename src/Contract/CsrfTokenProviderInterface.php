<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Dto\JoomlaClient;
use JoomlaCodeception\Dto\ModuleConfiguration;
use JoomlaCodeception\Service\DatabaseFixtureManager;

interface CsrfTokenProviderInterface
{
    public function token(
        PhpBrowser $browser,
        \PDO $database,
        DatabaseFixtureManager $fixtures,
        JoomlaClient $client,
        ?int $userId,
        ModuleConfiguration $configuration,
    ): string;
}
