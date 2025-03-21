<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression;

use LogicException;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\ColumnMap;

/**
 * Base class for various types of column expressions:
 *  - column comes from a table scan or join -> LocalColumnExpression
 *  - column comes from a subquery or parent query -> RemoteColumnExpression
 *  - column could not be resolved, possibly because it was not yet added to the query -> UnresolvedColumnExpression
 */
abstract class AbstractColumnExpression
{
    /**
     * The query object where the expression is used.
     *
     * @var Criteria
     */
    protected Criteria $sourceQuery;

    /**
     * @var string|null
     */
    protected $tableAlias;

    /**
     * @var string
     */
    protected $columnIdentifier;

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
     * @param mixed $tableAlias
     * @param string $columnIdentifier
     */
    public function __construct(Criteria $sourceQuery, ?string $tableAlias, string $columnIdentifier)
    {
        $this->sourceQuery = $sourceQuery;
        $this->columnIdentifier = $columnIdentifier;
        $this->tableAlias = $tableAlias;
    }

    /**
     * @param bool $useQuotesIfEnabled
     * @return string
     */
    public function getColumnExpressionInQuery(bool $useQuotesIfEnabled = false): string
    {
        $columnLiteral = $this->tableAlias ? "{$this->tableAlias}.{$this->columnIdentifier}" : $this->columnIdentifier;
        if (!$useQuotesIfEnabled) {
            return $columnLiteral;
        }
        $tableMap = $this->hasColumnMap() ? $this->getColumnMap()->getTableMap() : null;
 
        return $this->sourceQuery->quoteColumnIdentifier($columnLiteral, $tableMap);
    }

    /**
     * The raw column name, i.e. 'author_id' for 'Book.AuthorId'
     * @return string
     */
    public function getColumnIdentifier(): string
    {
        return $this->columnIdentifier;
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression $otherColumn
     * @return bool
     */
    public function equals(AbstractColumnExpression $otherColumn): bool
    {
        return $otherColumn instanceof static
            && $this->columnIdentifier === $otherColumn->columnIdentifier
            && $this->tableAlias === $otherColumn->tableAlias;
    }

    /**
     * @return Criteria
     */
    public function getSourceQuery(): ?Criteria
    {
        return $this->sourceQuery;
    }

    /**
     * @return string|null
     */
    public function getTableAlias(): ?string
    {
        return $this->tableAlias;
    }

    /**
     * @return string|null
     */
    public function hasColumnMap(): bool
    {
        return false;
    }

    /**
     * @return ColumnMap|null
     */
    public function getColumnMap(): ?ColumnMap
    {
        throw new LogicException('should never be called, guard by checking AbstractColumnExpression::hasColumnMap() first.');
    }
}
