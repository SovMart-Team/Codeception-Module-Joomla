<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use Codeception\Module\PhpBrowser;
use JoomlaCodeception\Contract\AuthenticationAdapterInterface;
use JoomlaCodeception\Dto\JoomlaClient;
use JoomlaCodeception\Dto\ModuleConfiguration;

final readonly class DefaultAuthenticationAdapter implements AuthenticationAdapterInterface
{
    public function login(PhpBrowser $browser, JoomlaClient $client, array $identity, ModuleConfiguration $configuration): void
    {
        if ($client === JoomlaClient::Api) {
            throw new \RuntimeException('API authentication requires a project authentication adapter.');
        }

        if ($client === JoomlaClient::Administrator) {
            $browser->amOnPage($configuration->administratorLoginRoute);
            $browser->seeElement('form#form-login');
            $browser->submitForm('form#form-login', [
                'username' => $identity['username'],
                'passwd'   => $identity['password'],
            ]);
            $browser->dontSeeElement('form#form-login');

            return;
        }

        $browser->amOnPage($configuration->siteLoginRoute);
        $browser->seeElement($configuration->siteLoginSelector);
        $browser->submitForm($configuration->siteLoginSelector, [
            $configuration->siteUsernameField => $identity['username'],
            $configuration->sitePasswordField => $identity['password'],
        ]);
    }
}
