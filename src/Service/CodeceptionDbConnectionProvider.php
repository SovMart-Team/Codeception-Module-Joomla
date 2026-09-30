<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use Codeception\Module\Db;
use JoomlaCodeception\Contract\DatabaseConnectionProviderInterface;
use JoomlaCodeception\Exception\UnsupportedEnvironmentException;

final readonly class CodeceptionDbConnectionProvider implements DatabaseConnectionProviderInterface
{
    public function __construct(private Db $database)
    {
        if (!method_exists($database, '_getDbh')) {
            throw new UnsupportedEnvironmentException('Installed Codeception Db module does not expose the required PDO bridge.');
        }
    }

    public function connection(): \PDO
    {
        $connection = $this->database->_getDbh();

        if (!$connection instanceof \PDO) {
            throw new UnsupportedEnvironmentException('Codeception Db module did not return a PDO connection.');
        }

        return $connection;
    }
}
