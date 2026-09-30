<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

final class MariaDbAdvisoryLock
{
    private bool $acquired = false;

    public function __construct(
        private readonly \PDO $database,
        private readonly CleanupJournal $cleanupJournal,
        private readonly string $name,
        private readonly int $timeout,
    ) {
    }

    public function acquire(): void
    {
        if ($this->acquired || $this->name === '') {
            return;
        }

        $statement = $this->database->prepare('SELECT GET_LOCK(:name, :timeout)');
        $statement->execute(['name' => $this->name, 'timeout' => $this->timeout]);

        if ((int) $statement->fetchColumn() !== 1) {
            throw new \RuntimeException(sprintf('Unable to acquire MariaDB Joomla fixture lock %s.', $this->name));
        }

        $this->acquired = true;
        $this->cleanupJournal->register('MariaDB advisory lock ' . $this->name, function (): void {
            $statement = $this->database->prepare('SELECT RELEASE_LOCK(:name)');
            $statement->execute(['name' => $this->name]);
            $this->acquired = false;
        });
    }
}
