<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\ForeignKey;

class FkRelationCodeProducer extends AbstractRelationCodeProducer
{
 /**
  * @var \Propel\Generator\Model\ForeignKey
  */
    protected $relation;

    /**
     * @param \Propel\Generator\Model\ForeignKey $relation
     * @param \Propel\Generator\Builder\Om\ObjectBuilder $parentBuilder
     */
    public function __construct(ForeignKey $relation, ObjectBuilder $parentBuilder)
    {
        $this->relation = $relation;
        parent::__construct($relation->getTable(), $parentBuilder);
    }

    /**
     * Constructs variable name for fkey-related objects.
     *
     * @return string
     */
    public function getAttributeName(): string
    {
        return 'a' . $this->nameProducer->resolveRelationIdentifier($this->relation, false);
    }

    /**
     * @return void
     */
    public function registerTargetClasses(): void
    {
        $targetTable = $this->relation->getForeignTable();

        $this->declareClassFromBuilder($this->getNewStubObjectBuilder($targetTable), 'Child');
        $this->declareClassFromBuilder($this->getNewStubQueryBuilder($targetTable));
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addMethods(string &$script): void
    {
        $this->registerTargetClasses();

        $this->addMutator($script);
        $this->addAccessor($script);
    }

    /**
     * Adds the class attributes that are needed to store fkey related objects.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    public function addAttributes(string &$script): void
    {
        $className = $this->getClassNameFromTable($this->relation->getForeignTable());
        $varName = $this->getAttributeName();

        $script .= "
    /**
     * @var        $className|null
     */
    protected $" . $varName . ";
";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addOnReloadCode(string &$script): void
    {
        $varName = $this->getAttributeName();
        $script .= "
            \$this->" . $varName . ' = null;';
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addDeleteScheduledItemsCode(string &$script): void
    {
        $attributeName = $this->getAttributeName();
        $relationIdentifierSingular = $this->nameProducer->resolveRelationIdentifier($this->relation, false);

        $script .= "
    if (\$this->$attributeName !== null) {
        if (\$this->" . $attributeName . '->isModified() || $this->' . $attributeName . "->isNew()) {
            \$affectedRows += \$this->" . $attributeName . "->save(\$con);
        }
        \$this->set{$relationIdentifierSingular}(\$this->$attributeName);
    }
";
    }

    /**
     * @param string $script
     *
     * @return string
     */
    #[\Override]
    public function addClearReferencesCode(string &$script): string
    {
        $varName = $this->getAttributeName();
        $script .= "
        \$this->$varName = null;";

        return $varName;
    }

    /**
     * Adds the mutator (setter) method for setting an fkey related object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addMutator(string &$script): void
    {
        $fk = $this->relation;
        $fkTable = $fk->getForeignTable();
        $interface = $fk->getInterface();
        $relationIdentifierSingular = $fk->getIdentifier();
        $varName = '$' . lcfirst($relationIdentifierSingular);
        $reverseIdentifierSingular = $fk->getIdentifierReversed();
        $className = $interface
            ? $this->declareClass($interface)
            : $this->getClassNameFromTable($fkTable);

        $attributeName = $this->getAttributeName();

        $orNull = $fk->getLocalColumn()->isNotNull() ? '' : '|null';
        $setAdd = $fk->isLocalPrimaryKey() ? 'set' : 'add'; // one-to-one or one-to-many

        $script .= "
    /**
     * Declares an association between this object and a $className object.
     *
     * @param {$className}{$orNull} $varName
     * @return \$this The current object (for fluent API support)
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function set{$relationIdentifierSingular}(?$className $varName = null)
    {";

        foreach ($fk->getMapping() as $map) {
            [$column, $rightValueOrColumn] = $map;
            $columnName = $column->getPhpName();
            $valueVarName = '$' . lcfirst($columnName);

            if ($rightValueOrColumn instanceof Column) {
                $defaultValue = $this->objectBuilder->getDefaultValueString($column);
                $getterIdentifier = $rightValueOrColumn->getPhpName();
                $val = "{$varName}->get{$getterIdentifier}()";
            } else {
                $defaultValue = 'null';
                $val = var_export($rightValueOrColumn, true);
            }
            $script .= "
        $valueVarName = $varName ? $val : $defaultValue;
        \$this->set{$columnName}($valueVarName);";
        }

        $script .= "

        \$this->$attributeName = $varName;
        {$varName}?->{$setAdd}{$reverseIdentifierSingular}(\$this);

        return \$this;
    }\n";
    }

    /**
     * Adds the accessor (getter) method for getting an fkey related object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addAccessor(string &$script): void
    {
        $fk = $this->relation;
        $varName = $this->getAttributeName();
        $fkQueryBuilder = $this->getNewStubQueryBuilder($fk->getForeignTable());
        $fkObjectBuilder = $this->getNewObjectBuilder($fk->getForeignTable())->getStubObjectBuilder();
        $returnDesc = '';
        $relationIdentifierSingular = $fk->getIdentifier();

        $relationIdentifierReversedSingular = $fk->getIdentifierReversed();
        $relationIdentifierReversedPlural = $fk->getIdentifierReversed($this->getPluralizer());

        $interface = $fk->getInterface();

        if ($interface) {
            $className = $this->declareClass($interface);
        } else {
            $className = $this->getClassNameFromBuilder($fkObjectBuilder); // get the ClassName that has maybe a prefix
            $returnDesc = "The associated $className object.";
        }

        $and = '';
        $conditional = '';
        $localColumns = []; // foreign key local attributes names

        // If the related columns are a primary key on the foreign table
        // then use findPk() instead of doSelect() to take advantage
        // of instance pooling
        $findPk = $fk->isForeignPrimaryKey();

        foreach ($fk->getMapping() as $mapping) {
            [$column, $rightValueOrColumn] = $mapping;

            $cptype = $column->getPhpType();
            $clo = $column->getLowercasedName();

            if ($rightValueOrColumn instanceof Column) {
                $localColumns[$rightValueOrColumn->getPosition()] = '$this->' . $clo;

                if ($cptype === 'int' || $cptype === 'float' || $cptype === 'double') {
                    $conditional .= $and . '$this->' . $clo . ' != 0';
                } elseif ($cptype === 'string') {
                    $conditional .= $and . '($this->' . $clo . ' !== "" && $this->' . $clo . ' !== null)';
                } else {
                    $conditional .= $and . '$this->' . $clo . ' !== null';
                }
            } else {
                $val = var_export($rightValueOrColumn, true);
                $conditional .= $and . '$this->' . $clo . ' === ' . $val;
            }

            $and = ' && ';
        }

        ksort($localColumns); // restoring the order of the foreign PK
        $localColumns = count($localColumns) > 1 ?
            ('array(' . implode(', ', $localColumns) . ')') : reset($localColumns);

        $orNull = $fk->getLocalColumn()->isNotNull() ? '' : '|null';
        $queryClassName = $this->getClassNameFromBuilder($fkQueryBuilder);

        $script .= "

    /**
     * Get the associated $className object
     *
     * @param ConnectionInterface \$con Optional Connection object.
     *
     *  @return {$className}{$orNull} $returnDesc
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function get{$relationIdentifierSingular}(?ConnectionInterface \$con = null)
    {";
        $script .= "
        if (\$this->$varName === null && ($conditional)) {";
        if ($findPk) {
            $script .= "
            \$this->$varName = {$queryClassName}::create()->findPk($localColumns, \$con);";
        } else {
            $script .= "
            \$this->$varName = {$queryClassName}::create()
                ->filterBy{$relationIdentifierReversedSingular}(\$this) // here
                ->findOne(\$con);";
        }
        if ($fk->isLocalPrimaryKey()) {
            $script .= "
            // Because this foreign key represents a one-to-one relationship, we will create a bi-directional association.
            \$this->{$varName}->set{$relationIdentifierReversedSingular}(\$this);";
        } else {
            $script .= "
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                \$this->{$varName}->add{$relationIdentifierReversedPlural}(\$this);
             */";
        }

        $script .= "
        }

        return \$this->$varName;
    }
";
    }
}
