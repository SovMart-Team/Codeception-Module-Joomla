<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

final readonly class MockRouteManager
{
    public function __construct(private string $baseUrl)
    {
    }

    public function url(string $controller, string $method, string $case): string
    {
        return rtrim($this->baseUrl, '/')
            . '/' . $controller . '/' . $method . '/router.php?case=' . rawurlencode($case);
    }
}
