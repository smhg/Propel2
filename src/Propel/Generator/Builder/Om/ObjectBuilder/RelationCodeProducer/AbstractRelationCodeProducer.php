<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use LogicException;
use Propel\Generator\Builder\DataModelBuilder;
use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Model\Table;

/**
 * Generates a database loader file, which is used to register all table maps with the DatabaseMap.
 */
abstract class AbstractRelationCodeProducer extends DataModelBuilder
{
    /**
     * @var \Propel\Generator\Builder\Om\ObjectBuilder
     */
    protected $objectBuilder;

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Builder\Om\ObjectBuilder $parentBuilder
     *
     * @throws \LogicException
     */
    protected function __construct(Table $table, ObjectBuilder $parentBuilder)
    {
        parent::__construct($table, $parentBuilder->referencedClasses);
        $this->objectBuilder = $parentBuilder;
        if (!$parentBuilder->getGeneratorConfig()) {
            throw new LogicException('CodeProducer should not be created before GeneratorConfig is available.');
        }
        $this->init($this->getTable(), $parentBuilder->getGeneratorConfig());
    }

    /**
     * @param string $script
     *
     * @return void
     */
    abstract public function addMethods(string &$script): void;

    /**
     * Adds the class attributes that are needed to store fkey related objects.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    abstract public function addAttributes(string &$script): void;

    /**
     * @param string $script
     *
     * @return void
     */
    abstract public function addOnReloadCode(string &$script): void;

    /**
     * @param string $script
     *
     * @return void
     */
    abstract public function addDeleteScheduledItemsCode(string &$script): void;

    /**
     * @param string $script
     *
     * @return string
     */
    abstract public function addClearReferencesCode(string &$script): string;
}
