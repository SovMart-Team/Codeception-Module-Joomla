<?php

declare(strict_types=1);

namespace Example\Tests\Functional\Api\Controller;

use Example\Tests\Functional\Support\ApiTester;
use PHPUnit\Framework\Assert;

final class StatusCest
{
    public function returnsVersion(ApiTester $I): void
    {
        $I->openPageFromFixture('/api/index.php/v1/example/status');
        $I->seeResponseCodeIs(200);
        $document = json_decode($I->grabPageSource(), true, 512, JSON_THROW_ON_ERROR);
        Assert::assertSame('1.0.0', $document['data']['version'] ?? null);
        $I->checkResponseResults();
        $I->checkDbResults();
        $I->checkFileResults();
        $I->checkSideEffectResults();
    }
}
