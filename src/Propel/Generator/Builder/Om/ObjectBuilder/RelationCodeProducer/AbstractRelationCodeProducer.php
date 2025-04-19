<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Builder\DataModelBuilder;
use Propel\Generator\Model\Table;

/**
 * Generates a database loader file, which is used to register all table maps with the DatabaseMap.
 */
abstract class AbstractRelationCodeProducer extends DataModelBuilder
{
    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return array{string, string}
     */
    protected function resolveObjectCollectorClassNameAndType(Table $table = null): array
    {
        $table ??= $this->getTable();
        $builder = $this->builderFactory->createObjectCollectionBuilder($table);
        $fqcn = $builder->resolveTableCollectionClassNameFq();
        $className = $this->declareClass($fqcn);
        $typeString = $builder->resolveTableCollectionClassType();

        return [$className, $typeString];
    }
    
}
