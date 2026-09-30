<?php

declare(strict_types=1);

namespace Example\Tests\Fixtures\Functional\Administrator\Controller\Article\saveSucceeds;

use JoomlaCodeception\Contract\IdentityFixtureInterface;

final readonly class IdentityFixture implements IdentityFixtureInterface
{
    public function rootPermissions(): array
    {
        return ['core.login.admin' => 1];
    }

    public function componentPermissions(): array
    {
        return [
            'core.manage' => 1,
            'core.create' => 1,
        ];
    }
}
