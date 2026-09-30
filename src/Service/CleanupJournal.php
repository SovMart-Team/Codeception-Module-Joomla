<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

final class CleanupJournal
{
    /** @var list<array{label: string, cleanup: callable(): void}> */
    private array $entries = [];

    public function register(string $label, callable $cleanup): void
    {
        $this->entries[] = ['label' => $label, 'cleanup' => $cleanup];
    }

    public function cleanup(): void
    {
        for ($index = count($this->entries) - 1; $index >= 0; --$index) {
            $entry = $this->entries[$index];

            try {
                ($entry['cleanup'])();
            } catch (\Throwable $throwable) {
                // Keep the failed entry and every earlier entry for the next
                // attempt. Earlier entries can own guards (for example the
                // advisory lock) that must remain active until cleanup ends.
                $this->entries = array_slice($this->entries, 0, $index + 1);

                throw new \RuntimeException(
                    sprintf('Joomla test cleanup failed: %s: %s', $entry['label'], $throwable->getMessage()),
                    0,
                    $throwable,
                );
            }
        }

        $this->entries = [];
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }
}
