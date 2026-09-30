<?php

declare(strict_types=1);

namespace JoomlaCodeception\Tests\Unit;

require_once __DIR__ . '/SupportAutoload.php';

use JoomlaCodeception\Dto\DatabaseCondition;
use JoomlaCodeception\Dto\DatabaseOperator;
use JoomlaCodeception\Service\DatabaseConditionBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseConditionBuilderTest extends TestCase
{
    /** @return iterable<string, array{DatabaseCondition, string}> */
    public static function operators(): iterable
    {
        yield 'not equal' => [new DatabaseCondition(DatabaseOperator::NotEqual, 1), '`value` != :criteria_0'];
        yield 'less' => [new DatabaseCondition(DatabaseOperator::Less, 1), '`value` < :criteria_0'];
        yield 'less or equal' => [new DatabaseCondition(DatabaseOperator::LessOrEqual, 1), '`value` <= :criteria_0'];
        yield 'greater' => [new DatabaseCondition(DatabaseOperator::Greater, 1), '`value` > :criteria_0'];
        yield 'greater or equal' => [new DatabaseCondition(DatabaseOperator::GreaterOrEqual, 1), '`value` >= :criteria_0'];
        yield 'like' => [new DatabaseCondition(DatabaseOperator::Like, 'a%'), '`value` LIKE :criteria_0'];
        yield 'not like' => [new DatabaseCondition(DatabaseOperator::NotLike, 'a%'), '`value` NOT LIKE :criteria_0'];
        yield 'null' => [new DatabaseCondition(DatabaseOperator::IsNull), '`value` IS NULL'];
        yield 'not null' => [new DatabaseCondition(DatabaseOperator::IsNotNull), '`value` IS NOT NULL'];
        yield 'in' => [new DatabaseCondition(DatabaseOperator::In, [1, 2]), '`value` IN (:criteria_0_0, :criteria_0_1)'];
        yield 'not in' => [new DatabaseCondition(DatabaseOperator::NotIn, [1, 2]), '`value` NOT IN (:criteria_0_0, :criteria_0_1)'];
    }

    #[DataProvider('operators')]
    public function testBuildsDeclaredOperator(DatabaseCondition $condition, string $expectedSql): void
    {
        $result = (new DatabaseConditionBuilder())->build(['value' => $condition]);
        self::assertSame($expectedSql, $result['sql']);
    }

    public function testDefersSemanticJsonToNormalizedComparison(): void
    {
        $condition = new DatabaseCondition(DatabaseOperator::Equal, ['b' => 2, 'a' => 1], true);
        $result    = (new DatabaseConditionBuilder())->build(['payload' => $condition]);

        self::assertSame('1 = 1', $result['sql']);
        self::assertSame($condition, $result['semantic']['payload']);
    }
}
