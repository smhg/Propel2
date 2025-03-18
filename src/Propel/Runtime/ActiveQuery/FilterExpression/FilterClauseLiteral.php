<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidClauseException;
use Propel\Runtime\ActiveQuery\ModelCriteria;

/**
 * A full filter statement given as a string, i.e. `col = ?`, `col1 = col2`, `col IS NULL`, `1=1`
 */
class FilterClauseLiteral extends AbstractFilter
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
     * @param array<array> $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @throws \Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidClauseException

     * @return string
     */
    protected function buildFilterClause(array &$paramCollector): string
    {
        $columns = $this->query instanceof ModelCriteria ? $this->query->getColumnResolver() : [];

        $replacements = substr_count($this->clause, '?');
        if (!$replacements) {
            return $this->clause;
        }

        if (!$columns && $this->value) {
            throw new InvalidClauseException($this->clause . ' - Cannot find column to determine value type');
        }

        // single value

        if (!is_array($this->value)) {
            if ($replacements > 1) {
                throw new InvalidClauseException($this->clause . ' - Not enough values provided for clause');
            }
            $parameter = $this->buildParameterByPosition($columns, 0, $this->value);
            $placeholder = $this->addParameter($paramCollector, $parameter);

            return str_replace('?', $placeholder, $this->clause);
        }

        // array of values

        if ($replacements > 1 && $replacements !== count($this->value)) {
            throw new InvalidClauseException($this->clause . ' - Number of placeholders does not match number of values in clause.');
        }

        $placeholderCollector = $replacements === 1 ? [] : null;
        $clause = (string)$this->clause;
        $columnsMatchValues = count($columns) === count($this->value);
        foreach (array_values($this->value) as $index => $value) {
            $columnIndex = $columnsMatchValues ? $index : 0;
            $parameter = $this->buildParameterByPosition($columns, $columnIndex, $value);
            $placeholder = $this->addParameter($paramCollector, $parameter);
            if ($placeholderCollector) {
                $placeholderCollector[] = $placeholder;
            } else {
                $clause = str_replace('?', $placeholder, $clause);
            }
        }

        if ($placeholderCollector) {
            $placeholderList = '(' . implode(',', $placeholderCollector) . ')';
            $clause = str_replace('?', $placeholderList, $clause);
        }

        return $clause;
    }

    /**
     * @param array<\Propel\Runtime\ActiveQuery\Util\ResolvedColumn> $parsedColumns
     * @param int $position
     * @param mixed $value
     *
     * @return array
     */
    protected function buildParameterByPosition(array $parsedColumns, int $position, $value): array
    {
        $ix = count($parsedColumns) > 1 ? $position : 0;

        return [
            'table' => null,
            'column' => $parsedColumns[$ix],
            'value' => $value,
        ];
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
