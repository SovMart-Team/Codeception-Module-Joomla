<?php

declare(strict_types=1);

namespace JoomlaCodeception\Context;

final class JoomlaTestDiagnostics
{
    public function failure(string $action, string $cest, string $method, string $directory, \Throwable $throwable): string
    {
        return sprintf(
            'Joomla test action failed [action=%s, cest=%s, method=%s, fixtures=%s]: %s',
            $action,
            $cest,
            $method,
            $directory,
            $this->redact($throwable->getMessage()),
        );
    }

    public function redact(string $message): string
    {
        $message = preg_replace(
            '/(?i)\b(password|passwd|api[_-]?token|access[_-]?token|refresh[_-]?token|token|secret|session(?:_id)?|authorization|cookie)(\s*[=:]\s*)(?:(?:bearer|basic)\s+)?[^\s,;]+/',
            '$1$2[redacted]',
            $message,
        ) ?? $message;

        return preg_replace(
            '/(?i)("(?:password|passwd|api[_-]?token|access[_-]?token|refresh[_-]?token|token|secret|session(?:_id)?|authorization|cookie)"\s*:\s*)"[^"]*"/',
            '$1"[redacted]"',
            $message,
        ) ?? $message;
    }
}
