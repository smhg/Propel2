<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\Util;

use Propel\Runtime\ActiveQuery\BaseModelCriteria;
use Propel\Runtime\ActiveQuery\Exception\UnknownColumnException;
use Propel\Runtime\ActiveQuery\Exception\UnknownModelException;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Exception\PropelException;

class ColumnResolver
{
    /**
     * @var \Propel\Runtime\ActiveQuery\BaseModelCriteria
     */
    protected $query;

    /**
     * ColumnMap for columns found in statement
     *
     * @var array<\Propel\Runtime\ActiveQuery\Util\ResolvedColumn>
     */
    private $replacedColumns = [];

    /**
     * @param \Propel\Runtime\ActiveQuery\BaseModelCriteria $query
     */
    public function __construct(BaseModelCriteria $query)
    {
        $this->query = $query;
    }

    /**
     * Retrieve the first column found in SQL clause.
     *
     * Used when creating Criterions from clauses.
     *
     * @param string $clause
     *
     * @return \Propel\Runtime\ActiveQuery\Util\ResolvedColumn|null
     */
    public function resolveFirstColumn(string $clause): ?ResolvedColumn
    {
        $this->replaceColumnNames($clause);

        return $this->replacedColumns[0] ?? null;
    }

    /**
     * @deprecated old version of ColumnResolver::replaceColumnNames().
     *
     * @param string $sql SQL clause to inspect (modified by the method)
     *
     * @return bool Whether the method managed to find and replace at least one column name
     */
    public function replaceColumnNamesAndReturnIndicator(string &$sql): bool
    {
        $sql = $this->replaceColumnNames($sql);

        return count($this->replacedColumns) > 0;
    }

    /**
     * Replaces complete column names (like Article.AuthorId) in an SQL clause
     * by their exact Propel column fully qualified name (e.g. article.author_id).
     *
     * Ignores column names inside quotes.
     *
     * <code>
     * 'CONCAT(Book.AuthorID, "Book.AuthorID") = ?'
     *   => 'CONCAT(book.author_id, "Book.AuthorID") = ?'
     * </code>
     *
     * @param string $sql SQL clause to inspect
     *
     * @return string modified statement
     */
    public function replaceColumnNames(string $sql): string
    {
        $this->replacedColumns = [];

        $parsedString = ''; // collects the result
        $stringToTransform = ''; // collects substrings from input to be processed before written to result
        $len = strlen($sql);
        $pos = 0;
        // go through string, write text in quotes to output, rest is written after transform
        while ($pos < $len) {
            $char = $sql[$pos];

            if (($char !== "'" && $char !== '"') || ($pos > 0 && $sql[$pos - 1] === '\\')) {
                $stringToTransform .= $char;
            } else {
                // start of quote, process what was found so far
                $parsedString .= preg_replace_callback("/[\w\\\]+\.\w+/", [$this, 'doReplaceNameInExpression'], $stringToTransform);
                $stringToTransform = '';

                // copy to result until end of quote
                $openingQuoteChar = $char;
                $parsedString .= $char;
                while (++$pos < $len) {
                    $char = $sql[$pos];
                    $parsedString .= $char;
                    if ($char === $openingQuoteChar && $sql[$pos - 1] !== '\\') {
                        break;
                    }
                }
            }
            $pos++;
        }

        if ($stringToTransform) {
            $parsedString .= preg_replace_callback("/[\w\\\]+\.\w+/", [$this, 'doReplaceNameInExpression'], $stringToTransform);
        }

        return $parsedString;
    }

    /**
     * Callback function to replace column names by their real name in a clause
     * e.g. 'Book.Title IN ?'
     *    => 'book.title IN ?'
     *
     * @param array $matches Matches found by preg_replace_callback
     *
     * @return string the column name replacement
     */
    protected function doReplaceNameInExpression(array $matches): string
    {
        $key = $matches[0];
        $resolvedColumn = $this->resolveColumn($key);

        if (!$resolvedColumn->isFromLocalTable()) {
            return $this->query->quoteColumnIdentifier($resolvedColumn->getLocalColumnName() ?? $key);
        }

        $this->replacedColumns[] = $resolvedColumn;

        return $this->query->quoteColumnIdentifier($resolvedColumn->getLocalColumnName(), $resolvedColumn->getTableMap());
    }

    /**
     * Finds a column and a SQL translation for a pseudo SQL column name.
     *
     * Examples:
     * <code>
     * $c->resolveColumn('Book.Title');
     *   => new ResolvedColumn('book.title', $bookTitleColumnMap)
     *
     * $c->join('Book.Author a')->resolveColumn('a.FirstName');
     *   => new ResolvedColumn('a.first_name', $authorFirstNameColumnMap)
     * </code>
     *
     * @param string $columnName String representing the column name in a pseudo SQL clause, e.g. 'Book.Title'
     * @param bool $failSilently
     *
     * @throws \Propel\Runtime\ActiveQuery\Exception\UnknownColumnException
     * @throws \Propel\Runtime\ActiveQuery\Exception\UnknownModelException
     *
     * @return \Propel\Runtime\ActiveQuery\Util\ResolvedColumn
     */
    public function resolveColumn(string $columnName, bool $failSilently = true): ResolvedColumn
    {
        $query = $this->query;

        if (strpos($columnName, '.') === false) {
            $prefix = (string)$query->getModelAliasOrName();
        } else {
            // $prefix could be either class name or table name
            [$prefix, $columnName] = explode('.', $columnName);
        }

        [$tableAlias, $tableMap] = $this->findTableForColumnIdentifierInQuery($prefix, $query);
        $isColumnFound = (bool)$tableAlias;

        if ($tableAlias && !$tableMap) {
            // local column (join without model)
            return new ResolvedColumn("$tableAlias.$columnName", null, $tableAlias);
        }

        if (!$isColumnFound && $query->hasSelectQuery($prefix)) {
            return $this->getColumnFromSubQuery($prefix, $columnName, $failSilently);
        }

        if (!$isColumnFound && $query instanceof ModelCriteria && $query->getPrimaryCriteria()) {
            // Propel does not use alias on topmost query, so $prefix might be an unused alias - FIXME: use alias if specified
            $parentQuery = $query->getPrimaryCriteria();
            while ($parentQuery->getPrimaryCriteria()) {
                $parentQuery = $parentQuery->getPrimaryCriteria();
            }
            if ($prefix === $parentQuery->getModelAlias()) {
                $tableAlias = $parentQuery->getTableMap()->getName();

                return new ResolvedColumn("$tableAlias.$columnName", null, $tableAlias);
            }
        }

        if (!$tableMap) {
            if ($failSilently) {
                return ResolvedColumn::getEmptyResolvedColumn();
            }

            throw new UnknownModelException(sprintf('Unknown model, alias or table "%s"', $prefix));
        }

        $column = $tableMap->findColumnByName($columnName);

        if ($column !== null) {
            $columnName = $column->getName();

            return new ResolvedColumn("$tableAlias.$columnName", $column, $tableAlias);
        } elseif ($query->getColumnForAs($columnName)) {
            // local column
            return new ResolvedColumn($columnName);
        } elseif ($failSilently) {
            return ResolvedColumn::getEmptyResolvedColumn();
        } else {
            throw new UnknownColumnException(sprintf('Unknown column "%s" on model, alias or table "%s"', $columnName, $prefix));
        }
    }

    /**
     * @param string $columnIdentifier
     * @param \Propel\Runtime\ActiveQuery\BaseModelCriteria $query
     *
     * @return array{0: ?string, 1: \Propel\Runtime\Map\TableMap|null}
     */
    protected function findTableForColumnIdentifierInQuery(string $columnIdentifier, BaseModelCriteria $query): array
    {
        if (
            $columnIdentifier === $query->getModelAliasOrName()
            || $columnIdentifier === $query->getModelShortName()
            || ($query->getTableMap() && $columnIdentifier === $query->getTableMap()->getName())
        ) {
            // column name from Criteria's table
            $tableMap = $query->getTableMap();
            $tableAlias = $query->getTableNameInQuery();

            return [$tableAlias, $tableMap];
        }
        $join = $query->getJoinByIdentifier($columnIdentifier);
        if ($join) {
            // column of a relations's model
            $tableAlias = $join->getRightTableAliasOrName();
            $tableMap = ($join instanceof ModelJoin) ? $join->getTableMap() : null;

            return [$tableAlias, $tableMap];
        }

        return [null, null];
    }

    /**
     * Special case for subquery columns
     *
     * @param string $tableAlias
     * @param string $columnPhpName
     * @param bool $failSilently
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return \Propel\Runtime\ActiveQuery\Util\ResolvedColumn
     */
    protected function getColumnFromSubQuery(string $tableAlias, string $columnPhpName, bool $failSilently = true): ResolvedColumn
    {
        $subQueryCriteria = $this->query->getSelectQuery($tableAlias);
        $tableMap = $subQueryCriteria instanceof ModelCriteria ? $subQueryCriteria->getTableMap() : null;
        if ($tableMap && $tableMap->hasColumnByPhpName($columnPhpName)) {
            $column = $tableMap->getColumnByPhpName($columnPhpName);
            $localColumnName = $tableAlias . '.' . $column->getName();

            return new ResolvedColumn($localColumnName, null, $tableAlias);
        }
        if ($subQueryCriteria->getColumnForAs($columnPhpName) !== null) {
            // aliased column
            return new ResolvedColumn("$tableAlias.$columnPhpName");
        }
        if ($failSilently) {
            return ResolvedColumn::getEmptyResolvedColumn();
        }

        throw new PropelException(sprintf('Unknown column "%s" in the subQuery with alias "%s".', $columnPhpName, $tableAlias));
    }
}
