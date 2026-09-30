<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Contract\CsrfTokenProviderInterface;
use JoomlaCodeception\Dto\JoomlaClient;
use JoomlaCodeception\Dto\ModuleConfiguration;

final readonly class HtmlCsrfTokenProvider implements CsrfTokenProviderInterface
{
    public function token(
        PhpBrowser $browser,
        \PDO $database,
        DatabaseFixtureManager $fixtures,
        JoomlaClient $client,
        ?int $userId,
        ModuleConfiguration $configuration,
    ): string {
        $route = match ($client) {
            JoomlaClient::Administrator => $configuration->administratorTokenRoute,
            JoomlaClient::Site          => $configuration->siteTokenRoute,
            JoomlaClient::Api           => throw new \RuntimeException('API CSRF token requires a project token provider.'),
        };
        $browser->amOnPage($route);

        foreach ($browser->grabMultiple('input[type="hidden"][value="1"]', 'name') as $name) {
            if (is_string($name) && preg_match('/^[a-f0-9]{32}$/', $name)) {
                return $name;
            }
        }

        throw new \RuntimeException(sprintf('Unable to find Joomla CSRF token on %s.', $route));
    }
}
