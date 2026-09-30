<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

final readonly class JoomlaApiTokenFactory
{
    private const ALGORITHM = 'sha256';

    private const TOKEN_LENGTH = 32;

    /**
     * @return array{profileToken: string, bearerToken: string}
     */
    public function create(int $userId, string $projectRoot): array
    {
        if ($userId <= 0) {
            throw new \RuntimeException('Joomla API token user ID must be positive.');
        }

        $configurationFile = rtrim($projectRoot, '/') . '/configuration.php';

        if (!is_file($configurationFile) || !is_readable($configurationFile)) {
            throw new \RuntimeException(sprintf('Joomla configuration file is not readable: %s', $configurationFile));
        }

        $configuration = (new JoomlaConfigurationLoader())->load($configurationFile);
        $siteSecret    = (string) ($configuration->secret ?? '');

        if ($siteSecret === '') {
            throw new \RuntimeException('Joomla site secret must not be empty.');
        }

        $seed         = random_bytes(self::TOKEN_LENGTH);
        $profileToken = base64_encode($seed);
        $tokenHash    = hash_hmac(self::ALGORITHM, $seed, $siteSecret);
        $bearerToken  = base64_encode(self::ALGORITHM . ':' . $userId . ':' . $tokenHash);

        return compact('profileToken', 'bearerToken');
    }
}
