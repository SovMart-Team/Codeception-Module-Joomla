<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Contract\BrowserTransportInterface;
use JoomlaCodeception\Exception\UnsupportedEnvironmentException;

final readonly class PhpBrowserTransport implements BrowserTransportInterface
{
    public function __construct(private PhpBrowser $browser)
    {
        if (!method_exists($browser, '_request')) {
            throw new UnsupportedEnvironmentException('Installed PhpBrowser does not expose the required raw request bridge.');
        }
    }

    public function request(
        string $method,
        string $route,
        array $parameters = [],
        array $files = [],
        ?string $content = null,
        array $headers = [],
    ): void {
        foreach ($headers as $name => $value) {
            $this->browser->haveHttpHeader($name, $value);
        }

        try {
            $this->browser->_request(strtoupper($method), $route, $parameters, $files, [], $content);
        } finally {
            foreach (array_keys($headers) as $name) {
                // deleteHeader() is deprecated in newer InnerBrowser releases,
                // but remains the compatible API for the supported 4.0 line.
                $this->browser->deleteHeader($name);
            }
        }
    }

    public function responseHeader(string $name): ?string
    {
        $client = $this->browser->client;

        if ($client === null || !method_exists($client, 'getInternalResponse')) {
            throw new UnsupportedEnvironmentException('Installed PhpBrowser connector does not expose response headers.');
        }

        $value = $client->getInternalResponse()->getHeader($name);

        return is_string($value) ? $value : null;
    }
}
