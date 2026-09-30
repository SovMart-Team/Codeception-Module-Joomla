<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Contract\BrowserTransportInterface;
use JoomlaCodeception\Dto\CsrfMode;
use JoomlaCodeception\Dto\RequestData;
use JoomlaCodeception\Service\FormInspector;
use JoomlaCodeception\Service\FormSubmitter;
use JoomlaCodeception\Service\RouteHelper;
use PHPUnit\Framework\TestCase;

final class FormSubmitterTest extends TestCase
{
    public function testExcludesRealFormTokenFromNonFormCsrfModes(): void
    {
        foreach ([CsrfMode::Missing, CsrfMode::Invalid, CsrfMode::Query, CsrfMode::Header] as $mode) {
            $browser = $this->createMock(PhpBrowser::class);
            $token   = str_repeat('a', 32);
            $browser->expects(self::once())->method('amOnPage')->with('/form');
            $browser->expects(self::exactly(2))->method('grabMultiple')->willReturnOnConsecutiveCalls(
                [$token, 'return'],
                ['1', 'internal'],
            );
            $browser->method('grabAttributeFrom')->willReturnMap([
                ['#adminForm', 'action', '/submit'],
                ['#adminForm', 'method', 'post'],
            ]);
            $browser->expects(self::never())->method('submitForm');
            $transport = $this->createMock(BrowserTransportInterface::class);
            $transport->expects(self::once())->method('request')->with(
                'POST',
                self::anything(),
                self::callback(static fn (array $payload): bool => $mode === CsrfMode::Invalid
                    ? $payload[$token] === 0
                    : !array_key_exists($token, $payload)),
                [],
                null,
                self::anything(),
            );

            (new FormSubmitter($browser, new FormInspector($browser), $transport, new RouteHelper()))->submit(
                new RequestData(csrfMode: $mode),
                ['title' => 'Example'],
                '/form',
                '#adminForm',
            );
        }
    }

    public function testKeepsRealFormTokenForFormMode(): void
    {
        $browser = $this->createMock(PhpBrowser::class);
        $token   = str_repeat('b', 32);
        $browser->method('grabMultiple')->willReturnOnConsecutiveCalls([$token], ['1']);
        $browser->expects(self::once())->method('submitForm')->with('#adminForm', self::callback(
            static fn (array $payload): bool => $payload[$token] === '1',
        ));

        (new FormSubmitter(
            $browser,
            new FormInspector($browser),
            $this->createStub(BrowserTransportInterface::class),
            new RouteHelper(),
        ))->submit(
            new RequestData(csrfMode: CsrfMode::Form),
            [],
            '/form',
            '#adminForm',
        );
    }
}
