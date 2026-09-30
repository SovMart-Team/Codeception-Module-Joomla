<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Contract\BrowserTransportInterface;
use JoomlaCodeception\Dto\ControlFieldPolicy;
use JoomlaCodeception\Dto\CsrfMode;
use JoomlaCodeception\Dto\RequestData;

final readonly class FormSubmitter
{
    public function __construct(
        private PhpBrowser $browser,
        private FormInspector $inspector,
        private BrowserTransportInterface $transport,
        private RouteHelper $routes,
    ) {
    }

    /** @param array<string, mixed> $fields */
    public function submit(RequestData $request, array $fields, string $page, string $selector): void
    {
        $controlFields = $this->inspector->hiddenFields($page, $selector);

        $token         = $this->csrfFieldName($controlFields);
        $rawSubmission = in_array(
            $request->csrfMode,
            [CsrfMode::Missing, CsrfMode::Invalid, CsrfMode::Query, CsrfMode::Header],
            true,
        );

        if ($rawSubmission) {
            $controlFields = array_filter(
                $controlFields,
                static fn (string $name): bool => preg_match('/^[a-f0-9]{32}$/', $name) !== 1,
                ARRAY_FILTER_USE_KEY,
            );
        }

        $payload = match ($request->controlFieldPolicy) {
            ControlFieldPolicy::Merge       => array_replace_recursive($controlFields, $fields),
            ControlFieldPolicy::FixtureOnly => $fields,
            ControlFieldPolicy::Remove      => array_diff_key($fields, $controlFields),
        };

        if ($request->task !== null) {
            $payload['task'] = $request->task;
        }

        if (!$rawSubmission) {
            $this->browser->submitForm($selector, $payload);

            return;
        }

        $action  = $this->inspector->action($selector);
        $headers = [];

        if ($request->csrfMode === CsrfMode::Invalid && $token !== null) {
            $payload[$token] = 0;
        } elseif ($request->csrfMode === CsrfMode::Query && $token !== null) {
            $action = $this->routes->withQuery($action, [$token => 1]);
        } elseif ($request->csrfMode === CsrfMode::Header && $token !== null) {
            $headers['X-CSRF-Token'] = $token;
        }

        $this->transport->request($this->inspector->method($selector), $action, $payload, headers: $headers);
    }

    /** @param array<string, mixed> $fields */
    private function csrfFieldName(array $fields): ?string
    {
        foreach (array_keys($fields) as $name) {
            if (preg_match('/^[a-f0-9]{32}$/', $name) === 1) {
                return $name;
            }
        }

        return null;
    }
}
