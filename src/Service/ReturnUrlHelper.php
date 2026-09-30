<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

final readonly class ReturnUrlHelper
{
    public function encodeInternal(string $path): string
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            throw new \RuntimeException(sprintf('Return URL must be an internal absolute path: %s', $path));
        }

        return base64_encode($path);
    }
}
