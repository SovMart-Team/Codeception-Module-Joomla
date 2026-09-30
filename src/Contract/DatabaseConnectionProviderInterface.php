<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

interface DatabaseConnectionProviderInterface
{
    public function connection(): \PDO;
}
