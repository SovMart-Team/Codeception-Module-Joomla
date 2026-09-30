<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Contract\BrowserTransportInterface;
use JoomlaCodeception\Dto\CsrfMode;
use JoomlaCodeception\Dto\JoomlaClient;
use JoomlaCodeception\Dto\RedirectPolicy;
use JoomlaCodeception\Dto\RequestData;

final readonly class HttpRequestDispatcher
{
    public function __construct(
        private PhpBrowser $browser,
        private JoomlaSessionManager $session,
        private CsrfTokenManager $csrf,
        private RouteHelper $routes,
        private FormSubmitter $forms,
        private MultipartRequestBuilder $multipart,
        private MediaRequestBuilder $media,
        private MockRouteManager $mocks,
        private BrowserTransportInterface $transport,
    ) {
    }

    public function openPage(
        string $route,
        RequestData $request,
        FixtureProvider $provider,
        JoomlaClient $client,
        bool $authenticated,
        string $controller,
        string $method,
    ): void {
        [$route, $fields] = $this->prepare($route, $request, $provider, $client, $authenticated, $controller, $method);
        $this->withCsrfHeader(
            $request,
            $client,
            $authenticated,
            fn () => $this->browser->amOnPage($this->routes->withQuery($route, $fields)),
        );
    }

    public function sendAjaxPost(
        string $route,
        RequestData $request,
        FixtureProvider $provider,
        JoomlaClient $client,
        bool $authenticated,
        string $controller,
        string $method,
    ): void {
        [$route, $fields] = $this->prepare($route, $request, $provider, $client, $authenticated, $controller, $method);
        $this->withCsrfHeader(
            $request,
            $client,
            $authenticated,
            fn () => $this->browser->sendAjaxPostRequest($route, $fields),
        );
    }

    public function sendJson(
        string $route,
        RequestData $request,
        FixtureProvider $provider,
        JoomlaClient $client,
        bool $authenticated,
        string $controller,
        string $method,
    ): void {
        [$route, $fields] = $this->prepare($route, $request, $provider, $client, $authenticated, $controller, $method);
        $this->withCsrfHeader($request, $client, $authenticated, fn () => $this->transport->request(
            'POST',
            $route,
            content: json_encode($fields, JSON_THROW_ON_ERROR),
            headers: ['Content-Type' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
        ));
    }

    public function sendRequest(
        string $httpMethod,
        string $route,
        RequestData $request,
        FixtureProvider $provider,
        JoomlaClient $client,
        bool $authenticated,
        string $controller,
        string $method,
    ): void {
        $httpMethod = strtoupper($httpMethod);

        if (!in_array($httpMethod, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new \RuntimeException(sprintf('Unsupported Joomla fixture HTTP method: %s', $httpMethod));
        }

        [$route, $fields] = $this->prepare($route, $request, $provider, $client, $authenticated, $controller, $method);

        if ($httpMethod === 'GET') {
            $this->withCsrfHeader(
                $request,
                $client,
                $authenticated,
                fn () => $this->browser->amOnPage($this->routes->withQuery($route, $fields)),
            );

            return;
        }

        $this->withCsrfHeader(
            $request,
            $client,
            $authenticated,
            fn () => $this->transport->request($httpMethod, $route, $fields),
        );
    }

    public function submitForm(
        string $page,
        string $selector,
        RequestData $request,
        FixtureProvider $provider,
        JoomlaClient $client,
        bool $authenticated,
        string $controller,
        string $method,
    ): void {
        [$page, $fields] = $this->prepare($page, $request, $provider, $client, $authenticated, $controller, $method);
        $this->forms->submit($request, $fields, $page, $selector);
    }

    public function submitMultipart(
        string $route,
        RequestData $request,
        FixtureProvider $provider,
        JoomlaClient $client,
        bool $authenticated,
        string $controller,
        string $method,
    ): void {
        [$route, $fields] = $this->prepare($route, $request, $provider, $client, $authenticated, $controller, $method);
        $files            = $this->multipart->files($request, $provider);

        if (array_key_exists('MAX_FILE_SIZE', $fields) && $files !== []) {
            $multipart = $this->multipart->fieldsBeforeFilesBody($fields, $files);
            $this->withCsrfHeader(
                $request,
                $client,
                $authenticated,
                fn () => $this->transport->request(
                    'POST',
                    $route,
                    content: $multipart['body'],
                    headers: ['Content-Type' => $multipart['contentType']],
                ),
            );

            return;
        }

        $this->withCsrfHeader(
            $request,
            $client,
            $authenticated,
            fn () => $this->transport->request('POST', $route, $fields, $files),
        );
    }

    public function sendMedia(
        string $route,
        RequestData $request,
        FixtureProvider $provider,
        JoomlaClient $client,
        bool $authenticated,
        string $controller,
        string $method,
    ): void {
        [$route, $fields] = $this->prepare($route, $request, $provider, $client, $authenticated, $controller, $method);
        $fields           = $this->media->body($request, $provider, $fields);
        $this->withCsrfHeader($request, $client, $authenticated, fn () => $this->transport->request(
            'POST',
            $route,
            content: json_encode($fields, JSON_THROW_ON_ERROR),
            headers: ['Content-Type' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
        ));
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    private function prepare(
        string $route,
        RequestData $request,
        FixtureProvider $provider,
        JoomlaClient $client,
        bool $authenticated,
        string $controller,
        string $method,
    ): array {
        if ($route === '' || !str_starts_with($route, '/') || str_starts_with($route, '//')) {
            throw new \RuntimeException(sprintf('Joomla test route must be an internal absolute path: %s', $route));
        }

        $fields = $provider->resolve($request->fields);

        if (!is_array($fields)) {
            throw new \RuntimeException('Joomla request fields must resolve to an array.');
        }

        if ($request->selection !== null) {
            $resolvedIds = $provider->resolve($request->selection->ids);

            if (!is_array($resolvedIds) || !array_is_list($resolvedIds)) {
                throw new \RuntimeException('Joomla list selection ids must resolve to a list.');
            }

            $fields = array_replace($fields, $request->selection->fields($resolvedIds));
        }

        $fields = $this->csrf->apply($fields, $request->csrfMode, $client, $authenticated);

        if ($authenticated && $request->csrfMode === CsrfMode::Query) {
            $token = $this->session->csrfToken($client);
            unset($fields[$token]);
            $route = $this->routes->withQuery($route, [$token => 1]);
        }

        if ($request->mockCase !== null) {
            $fields['url'] = $this->mocks->url($controller, $method, $request->mockCase);
        }

        if ($request->redirectPolicy === RedirectPolicy::Stop) {
            $this->browser->stopFollowingRedirects();
        } else {
            $this->browser->startFollowingRedirects();
        }

        return [$route, $fields];
    }

    private function withCsrfHeader(
        RequestData $request,
        JoomlaClient $client,
        bool $authenticated,
        callable $send,
    ): void {
        if (!$authenticated || $request->csrfMode !== CsrfMode::Header) {
            $send();

            return;
        }

        $name = 'X-CSRF-Token';
        $this->browser->haveHttpHeader($name, $this->session->csrfToken($client));

        try {
            $send();
        } finally {
            // Keep compatibility with the minimum supported InnerBrowser 4.0.
            $this->browser->deleteHeader($name);
        }
    }
}
