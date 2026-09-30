<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

use JoomlaCodeception\Context\JoomlaTestDiagnostics;
use PHPUnit\Framework\TestCase;

final class JoomlaTestDiagnosticsTest extends TestCase
{
    public function testRedactsKeyValueBearerAndJsonSecrets(): void
    {
        $message = implode(' ', [
            'password=plain-password',
            'Authorization: Bearer bearer-token',
            'apiToken=api-token',
            '{"token":"json-token"}',
            'cookie:session-cookie',
        ]);
        $redacted = (new JoomlaTestDiagnostics())->redact($message);

        foreach (['plain-password', 'bearer-token', 'api-token', 'json-token', 'session-cookie'] as $secret) {
            self::assertStringNotContainsString($secret, $redacted);
        }

        self::assertGreaterThanOrEqual(5, substr_count($redacted, '[redacted]'));
    }
}
