<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Service\PhpBrowserTransport;
use PHPUnit\Framework\TestCase;

final class PhpBrowserTransportTest extends TestCase
{
    public function testRemovesPerRequestHeadersAfterSuccessfulRequest(): void
    {
        $browser = $this->browser(false);
        $browser->expects(self::once())->method('haveHttpHeader')->with('X-Test', 'temporary');
        $browser->expects(self::once())->method('deleteHeader')->with('X-Test');

        (new PhpBrowserTransport($browser))->request('POST', '/endpoint', headers: ['X-Test' => 'temporary']);
    }

    public function testRemovesPerRequestHeadersAfterFailedRequest(): void
    {
        $browser = $this->browser(true);
        $browser->expects(self::once())->method('haveHttpHeader')->with('X-Test', 'temporary');
        $browser->expects(self::once())->method('deleteHeader')->with('X-Test');

        $this->expectException(\RuntimeException::class);
        (new PhpBrowserTransport($browser))->request('POST', '/endpoint', headers: ['X-Test' => 'temporary']);
    }

    private function browser(bool $fail): PhpBrowser
    {
        $browser = $this->getMockBuilder(PhpBrowser::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['haveHttpHeader', 'deleteHeader', '_request'])
            ->getMock();
        $request = $browser->expects(self::once())->method('_request');

        if ($fail) {
            $request->willThrowException(new \RuntimeException('request failed'));
        }

        return $browser;
    }
}
