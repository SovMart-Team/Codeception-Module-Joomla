<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Contract\CsrfTokenProviderInterface;
use JoomlaCodeception\Dto\JoomlaClient;
use JoomlaCodeception\Dto\ModuleConfiguration;

final readonly class MariaDbSessionCsrfTokenProvider implements CsrfTokenProviderInterface
{
    public function token(
        PhpBrowser $browser,
        \PDO $database,
        DatabaseFixtureManager $fixtures,
        JoomlaClient $client,
        ?int $userId,
        ModuleConfiguration $configuration,
    ): string {
        if ($client === JoomlaClient::Api) {
            throw new \RuntimeException('API CSRF token requires a project token provider.');
        }

        if ($userId !== null && $userId > 0) {
            $statement = $database->prepare(sprintf(
                'SELECT `data` FROM `%s` WHERE `userid` = :userId AND `client_id` = :clientId ORDER BY `time` DESC LIMIT 1',
                $fixtures->table('#__session'),
            ));
            $statement->execute(['userId' => $userId, 'clientId' => $client === JoomlaClient::Administrator ? 1 : 0]);
            $sessionData = $statement->fetchColumn();

            if (is_string($sessionData) && preg_match('/joomla\|s:\d+:"([^"]+)";/', $sessionData, $matches)) {
                $decoded = base64_decode($matches[1], true);

                if (is_string($decoded) && preg_match('/s:5:"token";s:32:"([a-f0-9]{32})"/', $decoded, $matches)) {
                    $configurationFile = rtrim($configuration->projectRoot, '/') . '/configuration.php';

                    $joomlaConfiguration = (new JoomlaConfigurationLoader())->load($configurationFile);

                    return md5((string) $joomlaConfiguration->secret . $userId . $matches[1]);
                }
            }
        }

        return (new HtmlCsrfTokenProvider())->token(
            $browser,
            $database,
            $fixtures,
            $client,
            $userId,
            $configuration,
        );
    }
}
