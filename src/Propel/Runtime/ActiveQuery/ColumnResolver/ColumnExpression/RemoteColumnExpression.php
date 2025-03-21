<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression;

use Propel\Runtime\ActiveQuery\Criteria;

/**
 * A column that comes from a subquery or parent query and has no type information.
 */
class RemoteColumnExpression extends AbstractColumnExpression
{
    /**
 * A column that comes from a subquery or parent query and has no type information.
     * @param string|null $tableAlias
     * @param \Propel\Runtime\Map\ColumnMap $columnMap
     */
    public function __construct(Criteria $sourceQuery, ?string $tableAlias, string $columnIdentifier)
    {
        parent::__construct($sourceQuery, $tableAlias, $columnIdentifier);
    }
}
