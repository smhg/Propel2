<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace Propel\Runtime\ActiveQuery\ColumnResolver;

use LogicException;
use Propel\Runtime\ActiveQuery\BaseModelCriteria;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\LocalColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\RemoteColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\RemoteTypedColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UnresolvedColumnExpression;
use Propel\Runtime\ActiveQuery\Criteria;
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
     * @var array<\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression>
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
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|null
     */
    public function resolveFirstColumn(string $clause): ?AbstractColumnExpression
    {
        $this->replaceColumnNames($clause);

        return $this->replacedColumns[0] ?? null;
    }

    /**
     * @param string $sql SQL clause to inspect (modified by the method)
     *
     * @return array<AbstractColumnExpression>
     */
    public function resolveColumnsAndAdjustExpressions(string &$sql): array
    {
        $sql = $this->replaceColumnNames($sql);

        return $this->replacedColumns;
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
     * @param bool $preferAsColumns Set to true if the SQL clause has access to AS clauses (i.e. HAVING, subquery)
     *
     * @return string modified statement
     */
    public function replaceColumnNames(string $sql, bool $preferAsColumns = false): string
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
/*
        if (!$resolvedColumn->isFromLocalTable()) {
            $this->replacedColumns[] = $resolvedColumn;
            return $this->query->quoteColumnIdentifier($resolvedColumn->getLocalColumnName() ?? $key);
        }
*/
        if (!$resolvedColumn instanceof UnresolvedColumnExpression) {
            $this->replacedColumns[] = $resolvedColumn;
        }
        
        return $resolvedColumn->getColumnExpressionInQuery(true);
    }

    /**
     *
     * @param string $columnIdentifier String representing the column name in a pseudo SQL clause, e.g. 'Book.Title'
     * @param bool $failSilently
     * @param bool $hasAccessToOutputColumns Set true if column is accessed from output of the query, i.e. in HAVING or when query is a subquery.  
     *
     * @throws \Propel\Runtime\ActiveQuery\Exception\UnknownColumnException
     * @throws \Propel\Runtime\ActiveQuery\Exception\UnknownModelException
     *
     * @return AbstractColumnExpression
     */
    public function resolveColumn(string $columnIdentifier, bool $failSilently = true, bool $hasAccessToOutputColumns = false): AbstractColumnExpression
    {
        $sourceQuery = $this->query;

        if ($hasAccessToOutputColumns && $sourceQuery->getColumnForAs($columnIdentifier)) {
            return new RemoteColumnExpression($sourceQuery, null, $columnIdentifier);
        }

        if (strpos($columnIdentifier, '.') === false) {
            $prefix = (string)$sourceQuery->getModelAliasOrName();
        } else {
            // $prefix could be either class name or table name
            [$prefix, $columnIdentifier] = explode('.', $columnIdentifier);
        }

        [$tableAlias, $tableMap] = $this->resolveTableIdentifierInQuery($prefix, $sourceQuery);
        $isColumnFound = (bool)$tableAlias;

        if ($tableAlias && !$tableMap) {
            // local column (join without model)
            return new RemoteColumnExpression($sourceQuery, $tableAlias, $columnIdentifier);
        }

        if (!$isColumnFound && $sourceQuery->hasSelectQuery($prefix)) {
            return $this->getColumnFromSubQuery($sourceQuery, $sourceQuery->getSelectQuery($prefix), $prefix, $columnIdentifier, $failSilently);
        }

        if (!$isColumnFound && $sourceQuery instanceof ModelCriteria && $sourceQuery->getPrimaryCriteria()) {
            $resolvedColumn = $this->getQueryFromOuterQuery($sourceQuery, $prefix, $columnIdentifier);
            if ($resolvedColumn) {
                return $resolvedColumn;
            }
        }

        if (!$tableMap) {
            if ($failSilently) {
                return new UnresolvedColumnExpression($sourceQuery, $tableAlias ?? $prefix, $columnIdentifier);
            }

            throw new UnknownModelException(sprintf('Unknown model, alias or table "%s"', $prefix));
        }

        $columnMap = $tableMap->findColumnByName($columnIdentifier);

        if ($columnMap !== null) {
            $columnIdentifier = $columnMap->getName();

            return new LocalColumnExpression($sourceQuery, $tableAlias, $columnMap);
        } elseif ($sourceQuery->getColumnForAs($columnIdentifier)) {
            // local column
            throw new LogicException('AS columns should not be resolved like this...');
        }
        
        if (!$failSilently) {
            throw new UnknownColumnException(sprintf('Unknown column "%s" on model, alias or table "%s"', $columnIdentifier, $prefix));
        }

        return new UnresolvedColumnExpression($sourceQuery, $tableAlias ?? $prefix, $columnIdentifier);
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ModelCriteria $sourceQuery
     * @param string $prefix
     * @param string $columnIdentifier
     *
     * @return AbstractColumnExpression|null
     */
    protected function getQueryFromOuterQuery(ModelCriteria $sourceQuery, string $prefix, string $columnIdentifier): ?AbstractColumnExpression
    {
        // HACK - Propel does not use alias on topmost query, so $prefix might be an unused alias - FIXME: use alias if specified

        if (!$sourceQuery->getPrimaryCriteria()) {
            return null;
        }

        $tableAlias = null;
        $tableMap = null;
        $parentQuery = $sourceQuery;
        while (!$tableAlias && $parentQuery->getPrimaryCriteria()) {
            $parentQuery = $parentQuery->getPrimaryCriteria();
            [$tableAlias, $tableMap] = $this->resolveTableIdentifierInQuery($prefix, $parentQuery);
        }

        if (!$tableAlias) {
            return null;
        }

        if (!$tableMap) {
            return new RemoteColumnExpression($sourceQuery, $tableAlias, $columnIdentifier);
        }

        if (!$parentQuery->getPrimaryCriteria() && $prefix === $parentQuery->getModelAlias() && $tableMap) {
            //$tableAlias = $tableMap->getName(); // outmost query don't use alias
        }

        $columnMap = $tableMap->findColumnByName($columnIdentifier);
        if ($columnMap) {
            $columnIdentifier = $columnMap->getName();
        }

        return new LocalColumnExpression($sourceQuery, $tableAlias, $columnMap);
    }

    /**
     * @param string $tableIdentifier
     * @param \Propel\Runtime\ActiveQuery\BaseModelCriteria $query
     *
     * @return array{0: ?string, 1: \Propel\Runtime\Map\TableMap|null}
     */
    protected function resolveTableIdentifierInQuery(string $tableIdentifier, BaseModelCriteria $query): array
    {
        if (
            $tableIdentifier === $query->getModelAliasOrName()
            || $tableIdentifier === $query->getModelShortName()
            || ($query->getTableMap() && $tableIdentifier === $query->getTableMap()->getName())
        ) {
            $tableMap = $query->getTableMap();
            $tableAlias = $query->getTableNameInQuery();

            return [$tableAlias, $tableMap];
        }
        $join = $query->getJoinByIdentifier($tableIdentifier);
        if ($join) {
            $tableAlias = $join->getRightTableAliasOrName();
            $tableMap = ($join instanceof ModelJoin) ? $join->getTableMap() : null;

            return [$tableAlias, $tableMap];
        }

        return [null, null];
    }

    /**
     * Special case for subquery columns
     *
     * @param Criteria $sourceQuery
     * @param Criteria $subquery
     * @param string $tableAlias
     * @param string $columnPhpName
     * @param bool $failSilently
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return AbstractColumnExpression
     */
    protected function getColumnFromSubQuery(Criteria $sourceQuery, Criteria $subQuery, string $tableAlias, string $columnPhpName, bool $failSilently = true): AbstractColumnExpression
    {
        if ($subQuery->getColumnForAs($columnPhpName) !== null) {
            return new RemoteColumnExpression($sourceQuery, $tableAlias, $columnPhpName);
        }

        $tableMap = $subQuery instanceof ModelCriteria ? $subQuery->getTableMap() : null;
        if ($tableMap && $tableMap->hasColumnByPhpName($columnPhpName)) {
            $columnMap = $tableMap->getColumnByPhpName($columnPhpName);

            return new RemoteTypedColumnExpression($sourceQuery, $tableAlias, $columnMap->getName(), $columnMap->getPdoType(), $columnMap);
        }

        if (!$failSilently) {
            throw new PropelException(sprintf('Unknown column "%s" in the subQuery with alias "%s".', $columnPhpName, $tableAlias));
        }

        return new UnresolvedColumnExpression($sourceQuery, $tableAlias, $columnPhpName);
    }
}
