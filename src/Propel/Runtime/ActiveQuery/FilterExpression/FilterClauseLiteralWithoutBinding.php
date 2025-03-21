<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidClauseException;
use Propel\Runtime\ActiveQuery\ModelCriteria;

/**
 * A full filter statement given as a string, i.e. `col = ?`, `col1 = col2`, `col IS NULL`, `1=1`
 *
 * Column expression in clause must be resolvable by ColumnResolver::resolveColumns(), so
 * PDO types can be inferred.
 */
class FilterClauseLiteralWithoutBinding extends AbstractFilterClauseLiteral
{
    /**
     * @throws \Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidClauseException
     *
     * @return void
     */
    protected function resolveColumnsAndAdjustExpressions(): void
    {
        if ($this->resolvedColumns === null) {
            $this->resolvedColumns = $this->query instanceof ModelCriteria
                ? $this->query->getColumnResolver()->resolveColumnsAndAdjustExpressions($this->clause)
                : [];

            if (!$this->resolvedColumns && $this->value) {
                throw new InvalidClauseException("{$this->clause} - Cannot find column to determine value type");
            }
        }
    }

    /**
     * @param array<array> $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @return string
     */
    protected function buildFilterClause(array &$paramCollector): string
    {
        $this->resolveColumnsAndAdjustExpressions();

        return parent::buildFilterClause($paramCollector);
    }

    /**
     * @see FilterClauseLiteral::buildParameterByPosition()
     *
     * @param int $position
     * @param mixed $value
     *
     * @return array{table: null, column: \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression, value: mixed}
     */
    protected function buildParameterByPosition(int $position, $value): array
    {
        if ($this->resolvedColumns === null) {
            $this->resolveColumnsAndAdjustExpressions();
        }
        $columnIndex = count($this->resolvedColumns) === 1 ? 0 : $position;

        return [
            'table' => null,
            'column' => $this->resolvedColumns[$columnIndex],
            'value' => $value,
        ];
    }
}
