<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Dto\CsrfMode;
use JoomlaCodeception\Dto\JoomlaClient;

final readonly class CsrfTokenManager
{
    public function __construct(private JoomlaSessionManager $session)
    {
    }

    /** @param array<string, mixed> $fields @return array<string, mixed> */
    public function apply(array $fields, CsrfMode $mode, JoomlaClient $client, bool $authenticated): array
    {
        if ($mode === CsrfMode::Missing) {
            return $fields;
        }

        if ($mode === CsrfMode::Invalid) {
            if ($authenticated) {
                $fields[$this->session->csrfToken($client)] = 0;
            } else {
                $fields['invalid_csrf_token'] = 1;
            }

            return $fields;
        }

        if ($authenticated && in_array($mode, [CsrfMode::Auto, CsrfMode::Form, CsrfMode::Query, CsrfMode::Json], true)) {
            $fields[$this->session->csrfToken($client)] = 1;
        }

        return $fields;
    }
}
