<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\Criterion;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\FilterExpression\AbstractFilter;
use Propel\Runtime\ActiveQuery\FilterExpression\BinaryColumnFilter;
use Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilter;
use Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface;
use Propel\Runtime\ActiveQuery\FilterExpression\FilterClauseLiteral;
use Propel\Runtime\ActiveQuery\FilterExpression\InColumnFilter;
use Propel\Runtime\ActiveQuery\FilterExpression\LikeColumnFilter;
use Propel\Runtime\ActiveQuery\Util\ResolvedColumn;

/**
 * Creates Criterion objects, extracted from Criteria class
 */
class CriterionFactory
{
    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     * @param \Propel\Runtime\ActiveQuery\Util\ResolvedColumn|string|null $column
     * @param string|int|null $comparison
     * @param mixed $value
     *
     * @return \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface
     */
    public static function build(Criteria $criteria, $column, $comparison = null, $value = null): ColumnFilterInterface
    {
        if (is_int($comparison)) {
            // $comparison is a PDO::PARAM_* constant value
            // something like $c->add('foo like ?', '%bar%', PDO::PARAM_STR);
            $columnName = $column instanceof ResolvedColumn ? $column->getLocalColumnName() : $column; // TODO

            return new RawCriterion($criteria, $columnName, $value, $comparison);
        }

        if ($value instanceof Criteria) {
            $columnName = $column instanceof ResolvedColumn ? $column->getLocalColumnName() : $column; // TODO

            return static::buildCriterionWithCriteria($criteria, $columnName, $comparison, $value);
        } elseif ($comparison === null) {
            $comparison = Criteria::EQUAL;
        }

        if (is_string($column)) {
            return static::buildCriterion($criteria, $column, $comparison, $value);
        } else {
            return static::buildFilterCondition($criteria, $column, $comparison, $value);
        }
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $query
     * @param \Propel\Runtime\ActiveQuery\Util\ResolvedColumn|null $column
     * @param string|null $comparison
     * @param mixed $value
     *
     * @return \Propel\Runtime\ActiveQuery\FilterExpression\AbstractFilter
     */
    protected static function buildFilterCondition(Criteria $query, ?ResolvedColumn $column, $comparison = null, $value = null): AbstractFilter
    {
        switch ($comparison) {
            case Criteria::IN:
            case Criteria::NOT_IN:
                // table.column IN (?, ?) or table.column NOT IN (?, ?)
                // something like $c->add(BookTableMap::TITLE, array('foo', 'bar'), Criteria::IN);
                return new InColumnFilter($query, $column, $comparison, $value);
            case Criteria::LIKE:
            case Criteria::NOT_LIKE:
            case Criteria::ILIKE:
            case Criteria::NOT_ILIKE:
                // table.column LIKE ? or table.column NOT LIKE ?  (or ILIKE for Postgres)
                // something like $c->add(BookTableMap::TITLE, 'foo%', Criteria::LIKE);
                return new LikeColumnFilter($query, $column, $comparison, $value);
            case Criteria::BINARY_NONE:
            case Criteria::BINARY_ALL:
                // table.column & ? = 0 (Similar to  "NOT IN")
                // something like $c->add(BookTableMap::SOME_ARRAY_VAR, 26, Criteria::BINARY_NONE);
                return new BinaryColumnFilter($query, $column, $comparison, $value);
            case Criteria::CUSTOM:
                // custom expression with no parameter binding
                // something like $c->add(BookTableMap::TITLE, "CONCAT(book.TITLE, 'bar') = 'foobar'", Criteria::CUSTOM);
                return new FilterClauseLiteral($query, $value);
            default:
                // simple comparison
                // something like $c->add(BookTableMap::PRICE, 12, Criteria::GREATER_THAN);
                return new ColumnFilter($query, $column, $comparison, $value);
        }
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     * @param string $column
     * @param string|null $comparison
     * @param mixed $value
     *
     * @return \Propel\Runtime\ActiveQuery\Criterion\BasicCriterion|\Propel\Runtime\ActiveQuery\Criterion\BinaryCriterion|\Propel\Runtime\ActiveQuery\Criterion\CustomCriterion|\Propel\Runtime\ActiveQuery\Criterion\InCriterion|\Propel\Runtime\ActiveQuery\Criterion\LikeCriterion
     */
    protected static function buildCriterion(Criteria $criteria, $column, ?string $comparison = null, $value = null): AbstractCriterion
    {
        switch ($comparison) {
            case Criteria::CUSTOM:
                // custom expression with no parameter binding
                // something like $c->add(BookTableMap::TITLE, "CONCAT(book.TITLE, 'bar') = 'foobar'", Criteria::CUSTOM);
                return new CustomCriterion($criteria, $value);
            case Criteria::IN:
            case Criteria::NOT_IN:
                // table.column IN (?, ?) or table.column NOT IN (?, ?)
                // something like $c->add(BookTableMap::TITLE, array('foo', 'bar'), Criteria::IN);
                return new InCriterion($criteria, $column, $value, $comparison);
            case Criteria::LIKE:
            case Criteria::NOT_LIKE:
            case Criteria::ILIKE:
            case Criteria::NOT_ILIKE:
                // table.column LIKE ? or table.column NOT LIKE ?  (or ILIKE for Postgres)
                // something like $c->add(BookTableMap::TITLE, 'foo%', Criteria::LIKE);
                return new LikeCriterion($criteria, $column, $value, $comparison);
            case Criteria::BINARY_NONE:
            case Criteria::BINARY_ALL:
                // table.column & ? = 0 (Similar to  "NOT IN")
                // something like $c->add(BookTableMap::SOME_ARRAY_VAR, 26, Criteria::BINARY_NONE);
                return new BinaryCriterion($criteria, $column, $value, $comparison);
            default:
                // simple comparison
                // something like $c->add(BookTableMap::PRICE, 12, Criteria::GREATER_THAN);
                return new BasicCriterion($criteria, $column, $value, $comparison);
        }
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     * @param string $column
     * @param string|null $comparison
     * @param \Propel\Runtime\ActiveQuery\Criteria $innerQuery
     *
     * @return \Propel\Runtime\ActiveQuery\Criterion\AbstractInnerQueryCriterion
     */
    protected static function buildCriterionWithCriteria(
        Criteria $criteria,
        string $column,
        ?string $comparison,
        Criteria $innerQuery
    ): AbstractInnerQueryCriterion {
        if ($comparison === null) {
            $comparison = Criteria::IN;
        }

        switch ($comparison) {
            case ExistsQueryCriterion::TYPE_EXISTS:
            case ExistsQueryCriterion::TYPE_NOT_EXISTS:
                return new ExistsQueryCriterion($criteria, null, $comparison, $innerQuery);
            default:
                return new ColumnToQueryOperatorCriterion($criteria, $column, $comparison, $innerQuery);
        }
    }
}
