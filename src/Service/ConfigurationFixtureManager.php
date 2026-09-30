<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Contract\ConfigurationFixtureApplierInterface;

final readonly class ConfigurationFixtureManager implements ConfigurationFixtureApplierInterface
{
    public function __construct(
        private \PDO $database,
        private DatabaseFixtureManager $fixtures,
        private CleanupJournal $cleanupJournal,
        private string $component,
    ) {
    }

    /** @param array<string, mixed> $values */
    public function apply(array $values): void
    {
        if ($values === []) {
            return;
        }

        $before  = $this->read();
        $decoded = json_decode($before, true, 512, JSON_THROW_ON_ERROR);
        $params  = is_array($decoded) ? $decoded : [];
        $this->write(json_encode(array_replace($params, $values), JSON_THROW_ON_ERROR));
        $this->cleanupJournal->register($this->component . ' configuration', fn () => $this->write($before));
    }

    private function read(): string
    {
        $statement = $this->database->prepare(sprintf(
            'SELECT `params` FROM `%s` WHERE `type` = :type AND `element` = :element LIMIT 1',
            $this->fixtures->table('#__extensions'),
        ));
        $statement->execute(['type' => 'component', 'element' => $this->component]);
        $params = $statement->fetchColumn();

        if (!is_string($params)) {
            throw new \RuntimeException(sprintf('%s component params are missing.', $this->component));
        }

        return $params;
    }

    private function write(string $params): void
    {
        $statement = $this->database->prepare(sprintf(
            'UPDATE `%s` SET `params` = :params WHERE `type` = :type AND `element` = :element',
            $this->fixtures->table('#__extensions'),
        ));
        $statement->execute(['params' => $params, 'type' => 'component', 'element' => $this->component]);
    }
}
