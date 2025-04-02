<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn;

use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\ColumnMap;

/**
 * Update column expression with a value.
 */
class UpdateColumn extends AbstractUpdateColumn
{
    /**
     * Build update column expression with a value.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
     * @param \Propel\Runtime\Map\ColumnMap|string $columnIdentifierOrMap
     * @param mixed $value
     * @param int|null $pdoType
     *
     * @throws \InvalidArgumentException
     *
     * @return self
     */
    public static function build(Criteria $sourceQuery, $columnIdentifierOrMap, $value, ?int $pdoType = null)
    {
        if ($columnIdentifierOrMap instanceof ColumnMap) {
            $columnMap = $columnIdentifierOrMap;
        } else {
            $resolvedColumn = $sourceQuery->resolveColumn($columnIdentifierOrMap);
            if (!$resolvedColumn->hasColumnMap()) {
                if ($pdoType === null) {
                    throw new InvalidArgumentException("Could not resolve column '$columnIdentifierOrMap', PDO type has to be set manually");
                }

                return new self($value, $sourceQuery, $resolvedColumn->getTableAlias(), $resolvedColumn->getColumnName(), $pdoType);
            }
            $columnMap = $resolvedColumn->getColumnMap();
        }

        return new self($value, $sourceQuery, $columnMap->getTableName(), $columnMap->getName(), $pdoType ?? $columnMap->getPdoType(), $columnMap);
    }

    /**
     * Replaces question mark symbols with positional parameter placeholders (i.e. ':p2' for the second update parameter)
     *
     * @param int $positionIndex
     *
     * @return string
     */
    public function buildExpression(int &$positionIndex): string
    {
        return ':p' . $positionIndex++;
    }
}
