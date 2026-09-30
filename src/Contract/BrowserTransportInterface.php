<?php

declare(strict_types=1);

namespace JoomlaCodeception\Contract;

interface BrowserTransportInterface
{
    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $files
     * @param array<string, string> $headers
     */
    public function request(
        string $method,
        string $route,
        array $parameters = [],
        array $files = [],
        ?string $content = null,
        array $headers = [],
    ): void;

    public function responseHeader(string $name): ?string;
}
