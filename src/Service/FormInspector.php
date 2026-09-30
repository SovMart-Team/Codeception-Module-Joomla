<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use Codeception\Module\PhpBrowser;

final readonly class FormInspector
{
    public function __construct(private PhpBrowser $browser)
    {
    }

    /** @return array<string, mixed> */
    public function hiddenFields(string $page, string $formSelector): array
    {
        $this->browser->amOnPage($page);
        $fields   = [];
        $selector = $formSelector . ' input[type="hidden"]';
        $names    = $this->browser->grabMultiple($selector, 'name');
        $values   = $this->browser->grabMultiple($selector, 'value');

        foreach ($names as $index => $name) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            $value         = $values[$index] ?? '';
            $fields[$name] = is_scalar($value) ? (string) $value : '';
        }

        return $fields;
    }

    public function action(string $formSelector): string
    {
        $action = $this->browser->grabAttributeFrom($formSelector, 'action');

        return is_string($action) && $action !== '' ? $action : '/';
    }

    public function method(string $formSelector): string
    {
        $method = $this->browser->grabAttributeFrom($formSelector, 'method');

        return is_string($method) && $method !== '' ? strtoupper($method) : 'GET';
    }
}
