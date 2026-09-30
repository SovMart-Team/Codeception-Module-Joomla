<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

final readonly class RouteHelper
{
    /** @param array<string, mixed> $query */
    public function withQuery(string $route, array $query): string
    {
        if ($query === []) {
            return $route;
        }

        return $route . (str_contains($route, '?') ? '&' : '?') . http_build_query($query);
    }
}
