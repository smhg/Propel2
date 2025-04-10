<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use LogicException;

class FilterCollectorCombiner extends FilterCollector
{
    /**
     * FilterCollector to combine complex filters on.
     *
     * @var \Propel\Runtime\ActiveQuery\FilterExpression\FilterCollectorCombiner|null
     */
    protected $combiner;

    /**
     * @var string|null
     */
    protected $combineAndOr;

    /**
     * @param string $andOr
     *
     * @return void
     */
    public function combineFilters(string $andOr): void
    {
        if ($this->combiner) {
            $this->combiner->combineFilters($andOr);
        } else {
            $this->combiner = new self();
            $this->combineAndOr = $andOr;
        }
    }

    /**
     * @return bool
     */
    public function endCombineFilters(): bool
    {
        if ($this->combiner === null) {
            return false;
        }
        if (!$this->combiner->endCombineFilters()) {
            $this->columnFilters = $this->mergeCombiner($this->columnFilters);
            $this->combiner = null;
            $this->combineAndOr = null;
        }

        return true;
    }

    /*
    (a1 && b1) || (a2 && b2)
    a1[ &&b1, ||a2[&&b2] ] -> ((a1 && b1) || (a2 && b2))

     */

    /**
     * @param array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface> $target
     *
     * @return array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    protected function mergeCombiner(array $target): array
    {
        if (!$this->combiner || $this->combiner->isEmpty()) {
            return $target;
        }
        $combinerFilters = $this->combiner->mergeCombiner($this->combiner->columnFilters);
        $firstFilter = array_shift($combinerFilters);
        foreach ($combinerFilters as $filter) {
            $firstFilter->addAnd($filter);
        }

        if ($this->combineAndOr === ClauseList::OR_OPERATOR_LITERAL && (bool)$target) {
            end($target)->addOr($firstFilter);
        } else {
            $target[] = $firstFilter;
        }

        return $target;
    }

    /**
     * @return array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    public function getColumnFilters(): array
    {
        return $this->mergeCombiner(parent::getColumnFilters());
    }

    /**
     * @param string $andOr
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface $filter
     * @param bool $preferColumnCondition Group AND-filter by column.
     *
     * @return void
     */
    public function addFilterWithConjunction(string $andOr, ColumnFilterInterface $filter, bool $preferColumnCondition = true): void
    {
        if ($this->combiner) {
            $this->combiner->addFilterWithConjunction($andOr, $filter, $preferColumnCondition);
        } else {
            parent::addFilterWithConjunction($andOr, $filter, $preferColumnCondition);
        }
    }

    /**
     * Method to return criteria related to columns in a table.
     *
     * @param string $columnName Column name.
     *
     * @return \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|null
     */
    public function findFilterByColumn(string $columnName): ?ColumnFilterInterface
    {
        return parent::findFilterByColumn($columnName) ?? ($this->combiner ? $this->combiner->findFilterByColumn($columnName) : null);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return parent::isEmpty() && (!$this->combiner || $this->combiner->isEmpty());
    }

    /**
     * @return void
     */
    public function clear()
    {
        parent::clear();
        $this->combiner = null;
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\FilterCollector $filterCollector
     * @param bool $isOr
     *
     * @return void
     */
    public function merge(FilterCollector $filterCollector, bool $isOr): void
    {
        if ($this->combiner) {
            $this->combiner->merge($filterCollector, $isOr);
        } else {
            parent::merge($filterCollector, $isOr);
            $this->combiner = $filterCollector instanceof FilterCollectorCombiner ? $filterCollector->combiner : null;
        }
    }

    /**
     * @return int
     */
    public function countColumnFilters(): int
    {
        return parent::countColumnFilters() + ($this->combiner ? $this->combiner->countColumnFilters() : 0);
    }

    /**
     * @return array<string>
     */
    public function getColumnExpressionsInQuery(): array
    {
        return array_merge(parent::getColumnExpressionsInQuery(), $this->combiner ? $this->combiner->getColumnExpressionsInQuery() : []);
    }

    /**
     * @return array<string, \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    public function getColumnFiltersByColumn(): array
    {
        return array_merge(parent::getColumnFiltersByColumn(), $this->combiner ? $this->combiner->getColumnFiltersByColumn() : []);
    }

    /**
     * @param string|null $defaultTableAlias
     *
     * @throws \LogicException
     *
     * @return array<string|null, array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>> array(table => array(table.column1, table.column2))
     */
    public function groupFiltersByTable(?string $defaultTableAlias): array
    {
        if ($this->combiner) {
            throw new LogicException('Cannot group filters with unfinished combine');
        }

        return parent::groupFiltersByTable($defaultTableAlias);
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\FilterCollector $collector
     *
     * @return bool
     */
    public function equals(FilterCollector $collector): bool
    {
        if (!parent::equals($collector)) {
            return false;
        }

        return $this->combiner
            ? $collector instanceof static && $collector->combiner && $this->combiner->equals($collector->combiner)
            : !$collector instanceof static || !$this->combiner;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return parent::__toString() . ($this->combiner ? ' AND (' . $this->combiner->__toString() . ' ... )' : '');
    }

    /**
     * @return void
     */
    public function __clone()
    {
        parent::__clone();
        $this->combiner = $this->combiner ? clone $this->combiner : null;
    }
}
