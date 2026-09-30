<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Contract\BrowserTransportInterface;
use JoomlaCodeception\Dto\ResponseExpectation;
use JoomlaCodeception\Dto\ResponseType;
use PHPUnit\Framework\Assert;

final readonly class ResponseAssertionManager
{
    public function __construct(
        private PhpBrowser $browser,
        private FileFixtureManager $files,
        private BrowserTransportInterface $transport,
    ) {
    }

    public function assert(ResponseExpectation $expectation): void
    {
        if ($expectation->type === ResponseType::Html) {
            $this->headerContains('Content-Type', 'text/html');
        } elseif ($expectation->type === ResponseType::Json) {
            $this->headerContains('Content-Type', 'application/json');
            Assert::assertIsArray(json_decode($this->browser->grabPageSource(), true, 512, JSON_THROW_ON_ERROR));
        } elseif ($expectation->type === ResponseType::Binary) {
            Assert::assertNotSame('', $this->browser->grabPageSource(), 'Binary response is empty.');
        }

        if ($expectation->loginForm) {
            $this->browser->seeInSource('form-login');
        }

        if ($expectation->contentTypeContains !== null) {
            $this->headerContains('Content-Type', $expectation->contentTypeContains);
        }

        if ($expectation->redirectContains !== null) {
            $this->headerContains('Location', $expectation->redirectContains);
        }

        foreach ($expectation->contains as $fragment) {
            $this->browser->seeInSource($fragment);
        }

        foreach ($expectation->notContains as $fragment) {
            $this->browser->dontSeeInSource($fragment);
        }

        if ($expectation->uploadedImageSuccess === null) {
            return;
        }

        $response = json_decode($this->browser->grabPageSource(), true, 512, JSON_THROW_ON_ERROR);
        Assert::assertIsArray($response);
        Assert::assertSame($expectation->uploadedImageSuccess, $response['success'] ?? null);
        Assert::assertIsArray($response['file'] ?? null);
        $imageUrl = (string) ($response['file']['url'] ?? '');

        if ($expectation->uploadedImageUrlPrefix !== null) {
            Assert::assertStringStartsWith($expectation->uploadedImageUrlPrefix, $imageUrl);
        }

        if ($expectation->uploadedImageUrlSuffix !== null) {
            Assert::assertStringEndsWith($expectation->uploadedImageUrlSuffix, $imageUrl);
        }

        foreach ($expectation->uploadedImageUrlNotContains as $fragment) {
            Assert::assertStringNotContainsString($fragment, $imageUrl);
        }

        Assert::assertFileExists($this->files->publicImagePath($imageUrl));
    }

    private function headerContains(string $name, string $expected): void
    {
        $value = $this->transport->responseHeader($name);
        Assert::assertIsString($value, sprintf('Response header %s is missing.', $name));
        Assert::assertStringContainsString($expected, $value);
    }
}
