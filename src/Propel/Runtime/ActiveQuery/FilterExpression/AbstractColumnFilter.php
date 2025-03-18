<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Util\ResolvedColumn;
use Propel\Runtime\Map\ColumnMap;

/**
 * Filter statement on a query
 */
abstract class AbstractColumnFilter extends AbstractFilter
{
    /**
     * @var \Propel\Runtime\ActiveQuery\Util\ResolvedColumn
     */
    protected $queryColumn;

    /**
     * Operator literal.
     *
     * @var string
     */
    protected $operator;

    /**
     * Create a new instance.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $query
     * @param \Propel\Runtime\ActiveQuery\Util\ResolvedColumn $columnIdentifier
     * @param string $operator
     * @param mixed $value
     */
    public function __construct(Criteria $query, ResolvedColumn $columnIdentifier, string $operator, $value)
    {
        parent::__construct($query, $value);
        $this->queryColumn = $columnIdentifier;
        $this->operator = $operator;
    }

    /**
     * @return string
     */
    public function getLocalColumnName(): string
    {
        return $this->getColumnLiteral();
    }

    /**
     * @return string
     */
    public function getQuotedColumnLiteral(): string
    {
        return $this->query->quoteColumnIdentifier($this->getColumnLiteral(), $this->queryColumn->getTableMap());
    }

    /**
     * @return \Propel\Runtime\ActiveQuery\Util\ResolvedColumn
     */
    public function getQueryColumn(): ResolvedColumn
    {
        return $this->queryColumn;
    }

    /**
     * @return string|null
     */
    public function getTableAlias(): ?string
    {
        return $this->queryColumn->getTableAlias();
    }

    /**
     * @return string
     */
    public function getColumnLiteral(): string
    {
        return $this->queryColumn->getQueryColumnLiteral();
    }

    /**
     * @return \Propel\Runtime\Map\ColumnMap|null
     */
    public function getColumnMap(): ?ColumnMap
    {
        return $this->queryColumn->getColumnMap();
    }

    /**
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @return array{column: string, table: ?string, value: mixed}
     */
    protected function buildParameter(): array
    {
        return $this->buildParameterWithValue($this->value);
    }

    /**
     * @param mixed $value
     *
     * @return array{table: ?string, column: string, value: mixed}
     */
    protected function buildParameterWithValue($value): array
    {
        return [
            'table' => $this->queryColumn->getLocalTableName(),
            'column' => $this->queryColumn->getColumnMap()->getName(),
            'value' => $value,
        ];
    }

    /**
     * @param array<array> $paramCollector A list to which Prepared Statement parameters will be appended
     * @param array|null $parameter Optional parameter to use instead of default
     *
     * @return string Positional variable in query for the added parameter
     */
    protected function addParameter(array &$paramCollector, ?array $parameter = null): string
    {
        $parameter = $parameter ?: $this->buildParameter();

        return parent::addParameter($paramCollector, $parameter);
    }

    /**
     * This method checks another Criteria to see if they contain
     * the same attributes and hashtable entries.
     *
     * @param object|null $filter
     *
     * @return bool
     */
    public function equals(?object $filter): bool
    {
        return parent::equals($filter)
            && $filter instanceof static
            && $this->operator === $filter->operator
            && $this->queryColumn->equals($filter->queryColumn);
    }

    /**
     * Ensures deep cloning of attached objects
     *
     * @return void
     */
    public function __clone()
    {
        parent::__clone();
        $this->queryColumn = clone $this->queryColumn;
    }
}
