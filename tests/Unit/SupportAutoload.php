<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2) . '/src/';

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'JoomlaCodeception\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = $root . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
