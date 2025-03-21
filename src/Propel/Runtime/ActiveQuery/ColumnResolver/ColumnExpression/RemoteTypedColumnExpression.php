<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\ColumnMap;

/**
 * A column that comes from a subquery or parent query with PDO type information available.
 */
class RemoteTypedColumnExpression extends RemoteColumnExpression
{
    /**
     * @var int
     */
    protected int $pdoType;
    
    /**
     * @var ColumnMap|null
     */
    protected ?ColumnMap $columnMap;

    /**
     * A column that comes from a subquery or parent query with PDO type information available.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
     * @param mixed $tableAlias
     * @param string $columnIdentifier
     * @param int $pdoType
     * @param ColumnMap|null $columnMap
     */
    public function __construct(Criteria $sourceQuery, ?string $tableAlias, string $columnIdentifier, int $pdoType, ?ColumnMap $columnMap = null)
    {
        parent::__construct($sourceQuery, $tableAlias, $columnIdentifier);
        $this->pdoType = $pdoType;
        $this->columnMap = $columnMap;
    }

    public function getColumnMap(): ?ColumnMap
    {
        return $this->columnMap;
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression $otherColumn
     * @return bool
     */
    public function equals(AbstractColumnExpression $otherColumn): bool
    {
        return $otherColumn instanceof static
            && parent::equals($otherColumn)
            && $this->pdoType === $otherColumn->pdoType
            && $this->columnMap === $otherColumn->columnMap
            ;
    }

    /**
     * @return bool
     */
    public function hasColumnMap(): bool
    {
        return (bool)$this->columnMap;
    }
}
