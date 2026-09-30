<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Dto\JoomlaClient;
use JoomlaCodeception\Dto\ModuleConfiguration;

interface AuthenticationAdapterInterface
{
    /** @param array<string, mixed> $identity */
    public function login(PhpBrowser $browser, JoomlaClient $client, array $identity, ModuleConfiguration $configuration): void;
}
