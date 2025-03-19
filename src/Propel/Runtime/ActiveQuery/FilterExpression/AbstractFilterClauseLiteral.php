<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidClauseException;

/**
 * A full filter statement given as a string, i.e. `col = ?`, `col1 = col2`, `col IS NULL`, `1=1`
 * 
 * Child classes have to figure out how to get PDO types for columns.
 */
abstract class AbstractFilterClauseLiteral extends AbstractFilter
{
    /**
     * @var string
     */
    protected $clause;

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $query
     * @param string $clause
     * @param mixed $value
     */
    public function __construct(Criteria $query, string $clause, $value = null)
    {
        parent::__construct($query, $value);
        $this->clause = $clause;
    }

    /**
     * Build parameter for Propel prepared statement.
     * 
     * @param int $position
     * @param mixed $value
     *
     * @return array{table: null, column?: string|\Propel\Runtime\ActiveQuery\Util\ResolvedColumn, type?: int, value: mixed}
     */
    abstract protected function buildParameterByPosition(int $position, $value): array;

    /**
     * @param array<array> $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @throws \Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidClauseException

     * @return string
     */
    protected function buildFilterClause(array &$paramCollector): string
    {
        $numberOfPlaceholders = substr_count($this->clause, '?');
        if ($numberOfPlaceholders === 0) {
            return $this->clause;
        }

        // single value

        if (!is_array($this->value)) {
            if ($numberOfPlaceholders > 1) {
                throw new InvalidClauseException($this->clause . ' - Not enough values provided for clause');
            }
            $parameter = $this->buildParameterByPosition(0, $this->value);
            $placeholder = $this->addParameter($paramCollector, $parameter);

            return str_replace('?', $placeholder, $this->clause);
        }

        // array of values

        if ($numberOfPlaceholders > 1 && $numberOfPlaceholders !== count($this->value)) {
            throw new InvalidClauseException($this->clause . ' - Number of placeholders does not match number of values in clause.');
        }

        $buildPlaceholderList = ($numberOfPlaceholders === 1); // single placeholder means we need a list ("col IN ?" => "col in (:p1, :p2)")
        $placeholderCollector = $buildPlaceholderList ? [] : null; 
        $clause = (string)$this->clause;
        foreach (array_values($this->value) as $columnIndex => $value) {
            $parameter = $this->buildParameterByPosition($columnIndex, $value);
            $placeholder = $this->addParameter($paramCollector, $parameter);
            if ($buildPlaceholderList) {
                $placeholderCollector[] = $placeholder;
            } else {
                $clause = str_replace('?', $placeholder, $clause);
            }
        }

        if ($buildPlaceholderList) {
            $placeholderList = '(' . implode(',', $placeholderCollector) . ')';
            $clause = str_replace('?', $placeholderList, $clause);
        }

        return $clause;
    }

    /**
     * @return string
     */
    public function getLocalColumnName(): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function getOperator(): string
    {
        return $this->clause;
    }

    /**
     * @return null
     */
    public function getTableAlias(): ?string
    {
        return null;
    }
}
