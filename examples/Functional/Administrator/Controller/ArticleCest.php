<?php

declare(strict_types=1);

namespace Example\Tests\Functional\Administrator\Controller;

use Example\Tests\Functional\Support\AdministratorTester;

final class ArticleCest
{
    public function saveSucceeds(AdministratorTester $I): void
    {
        $I->loadDbFixtures();
        $I->loginFromFixture();
        $I->submitFormFromFixture(
            '/administrator/index.php?option=com_example&view=article&layout=edit',
            '#adminForm',
        );
        $I->seeResponseCodeIs(303);
        $I->checkResponseResults();
        $I->checkDbResults();
        $I->checkFileResults();
        $I->checkSideEffectResults();
    }
}
