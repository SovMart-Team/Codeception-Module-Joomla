<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Exception\UnsupportedEnvironmentException;

final readonly class JoomlaConfigurationLoader
{
    public function load(string $configurationFile): object
    {
        $expectedFile = realpath($configurationFile);

        if ($expectedFile === false || !is_file($expectedFile) || !is_readable($expectedFile)) {
            throw new \RuntimeException(sprintf('Joomla configuration file is not readable: %s', $configurationFile));
        }

        if (!class_exists('JConfig', false)) {
            require_once $expectedFile;
        }

        if (!class_exists('JConfig', false)) {
            throw new \RuntimeException(sprintf('Joomla configuration class was not loaded from: %s', $expectedFile));
        }

        $reflection = new \ReflectionClass('JConfig');
        $loadedFile = $reflection->getFileName();
        $loadedFile = is_string($loadedFile) ? realpath($loadedFile) : false;

        if ($loadedFile !== $expectedFile) {
            throw new UnsupportedEnvironmentException(sprintf(
                'JConfig is already loaded from another Joomla root: expected %s, got %s. Run each Joomla root in a separate process.',
                $expectedFile,
                is_string($loadedFile) ? $loadedFile : 'an unknown source',
            ));
        }

        return new \JConfig();
    }
}
