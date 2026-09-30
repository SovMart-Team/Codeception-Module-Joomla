<?php

declare(strict_types=1);

namespace JoomlaCodeception\Service;

use JoomlaCodeception\Dto\DatabaseCondition;
use JoomlaCodeception\Dto\DatabaseOperator;

final readonly class DatabaseConditionBuilder
{
    /**
     * @param array<string, mixed> $criteria
     * @return array{sql: string, parameters: array<string, mixed>, semantic: array<string, DatabaseCondition>}
     */
    public function build(array $criteria, string $parameterPrefix = 'criteria_'): array
    {
        if ($criteria === []) {
            throw new \RuntimeException('Database criteria cannot be empty.');
        }

        $parts      = [];
        $parameters = [];
        $semantic   = [];
        $index      = 0;

        foreach ($criteria as $column => $rawCondition) {
            $this->assertIdentifier($column);
            $condition = $rawCondition instanceof DatabaseCondition
                ? $rawCondition
                : new DatabaseCondition(DatabaseOperator::Equal, $rawCondition);

            if ($condition->semanticJson) {
                if (!in_array($condition->operator, [DatabaseOperator::Equal, DatabaseOperator::NotEqual], true)) {
                    throw new \RuntimeException('Semantic JSON supports only equal and not-equal operators.');
                }

                $parts[]           = '1 = 1';
                $semantic[$column] = $condition;

                continue;
            }

            $parameter    = $parameterPrefix . $index++;
            $quotedColumn = sprintf('`%s`', $column);

            if ($condition->operator === DatabaseOperator::IsNull || ($condition->operator === DatabaseOperator::Equal && $condition->value === null)) {
                $parts[] = $quotedColumn . ' IS NULL';

                continue;
            }

            if ($condition->operator === DatabaseOperator::IsNotNull || ($condition->operator === DatabaseOperator::NotEqual && $condition->value === null)) {
                $parts[] = $quotedColumn . ' IS NOT NULL';

                continue;
            }

            if (in_array($condition->operator, [DatabaseOperator::In, DatabaseOperator::NotIn], true)) {
                if (!is_array($condition->value) || $condition->value === []) {
                    throw new \RuntimeException(sprintf('%s requires a non-empty array.', $condition->operator->value));
                }

                $placeholders = [];

                foreach (array_values($condition->value) as $valueIndex => $value) {
                    $itemParameter              = $parameter . '_' . $valueIndex;
                    $placeholders[]             = ':' . $itemParameter;
                    $parameters[$itemParameter] = $value;
                }

                $parts[] = sprintf('%s %s (%s)', $quotedColumn, $condition->operator->value, implode(', ', $placeholders));

                continue;
            }

            $parts[]                = sprintf('%s %s :%s', $quotedColumn, $condition->operator->value, $parameter);
            $parameters[$parameter] = $condition->value;
        }

        return ['sql' => implode(' AND ', $parts), 'parameters' => $parameters, 'semantic' => $semantic];
    }

    private function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $identifier)) {
            throw new \RuntimeException(sprintf('Invalid database column: %s', $identifier));
        }
    }
}
