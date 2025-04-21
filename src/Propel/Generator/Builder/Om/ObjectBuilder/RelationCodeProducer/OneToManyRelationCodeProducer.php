<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Model\ForeignKey;

/**
 * A one-to-many relation from an incoming FK.
 */
class OneToManyRelationCodeProducer extends AbstractIncomingRelationCode
{
    /**
     * Constructs variable name for fkey-related objects.
     *
     * @return string
     */
    public function getAttributeName(): string
    {
        return 'coll' . $this->relation->getIdentifierReversed($this->getPluralizer());
    }

    /**
     * @param string $script
     *
     * @return void
     */
    public function addMethods(string &$script): void
    {
        $this->registerTargetClasses();

        $this->addInit($script);
        $this->addPartial($script);
        $this->addClear($script);
        $this->addGet($script);
        $this->addSet($script);
        $this->addCount($script);
        $this->addAdd($script);
        $this->addDoAdd($script);
        $this->addRemove($script);
        $this->addGetJoinMethods($script);
    }

    /**
     * Adds the attributes used to store objects that have referrer fkey relationships to this object.
     * <code>protected collVarName;</code>
     * <code>private lastVarNameCriteria = null;</code>
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    public function addAttributes(string &$script): void
    {
        $className = $this->getClassNameFromTable($this->relation->getTable());
        $variable = '$' . $this->getAttributeName();
        [$_, $collectionType] = $this->resolveObjectCollectionClassNameAndType($this->relation->getTable());

        $script .= "
    /**
     * @var $collectionType|null Collection to store aggregation of $className objects.
     */
    protected $variable;

    /**
     * @var bool
     */
    protected {$variable}Partial;\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    public function addDeleteScheduledItemsCode(string &$script): void
    {
        $this->addScheduledForDeletion($script);

        $collName = $this->getAttributeName();
        $script .= "
    if (\$this->$collName !== null) {
        foreach (\$this->$collName as \$referrerFK) {
            if (!\$referrerFK->isDeleted() && (\$referrerFK->isNew() || \$referrerFK->isModified())) {
                \$affectedRows += \$referrerFK->save(\$con);
            }
        }
    }\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addScheduledForDeletion(string &$script): void
    {
        $refFK = $this->relation;
        $relatedName = $this->getRefFKPhpNameAffix($refFK, true);
        $lowerRelatedName = lcfirst($relatedName);
        $lowerSingleRelatedName = lcfirst($this->getRefFKPhpNameAffix($refFK, false));
        $queryClassName = $this->getNewStubQueryBuilder($refFK->getTable())->getClassname();

        $script .= "
            if (\$this->{$lowerRelatedName}ScheduledForDeletion !== null) {
                if (!\$this->{$lowerRelatedName}ScheduledForDeletion->isEmpty()) {";

        if ($refFK->isLocalColumnsRequired() || $refFK->getOnDelete() === ForeignKey::CASCADE) {
            $script .= "
                    $queryClassName::create()
                        ->filterByPrimaryKeys(\$this->{$lowerRelatedName}ScheduledForDeletion->getPrimaryKeys(false))
                        ->delete(\$con);";
        } else {
            $script .= "
                    foreach (\$this->{$lowerRelatedName}ScheduledForDeletion as \${$lowerSingleRelatedName}) {
                        // need to save related object because we set the relation to null
                        \${$lowerSingleRelatedName}->save(\$con);
                    }";
        }

        $script .= "
                    \$this->{$lowerRelatedName}ScheduledForDeletion = null;
                }
            }
";
    }

    /**
     * @param string $script
     *
     * @return string
     */
    public function addClearReferencesCode(string &$script): string
    {
        $attributeName = $this->getAttributeName();
        $script .= "
        if (\$this->$attributeName) {
            foreach (\$this->$attributeName as \$o) {
                \$o->clearAllReferences(\$deep);
            }
        }";

        return $attributeName;
    }

    /**
     * Adds the method that initializes the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addInit(string &$script): void
    {
        $table = $this->relation->getTable();
        $relationIdentifierPlural = $this->relation->getIdentifierReversed($this->getPluralizer());
        $attributeName = $this->getAttributeName();
        [$collectionClassName] = $this->resolveObjectCollectionClassNameAndType($table);
        $modelClassNameFq = $this->getClassNameFromBuilder($this->getNewStubObjectBuilder($table), true);

        $script .= "
    /**
     * Initializes the $attributeName collection.
     *
     * By default this just sets the $attributeName collection to an empty array (like clear$attributeName());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param bool \$overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function init$relationIdentifierPlural(bool \$overrideExisting = true): void
    {
        if (\$this->$attributeName !== null && !\$overrideExisting) {
            return;
        }

        \$this->{$attributeName} = new $collectionClassName();
        \$this->{$attributeName}->setModel('$modelClassNameFq');
    }
";
    }

    /**
     * Adds the method that clears the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addClear(string &$script): void
    {
        $relationIdentifierPlural = $this->relation->getIdentifierReversed($this->getPluralizer());
        $attributeName = $this->getAttributeName();

        $script .= "
    /**
     * Clears out the $attributeName collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return \$this
     */
    public function clear$relationIdentifierPlural(): static
    {
        \$this->$attributeName = null;

        return \$this;
    }\n";
    }

    /**
     * Adds the method that adds an object into the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addAdd(string &$script): void
    {
        $refFK = $this->relation;
        $tblFK = $refFK->getTable();

        $className = $this->getClassNameFromTable($refFK->getTable());

        if ($tblFK->getChildrenColumn()) {
            $className = $this->getClassNameFromTable($refFK->getTable()); // same as above?
        }
        $modelVar = '$' . lcfirst($className);

        $attributeName = $this->getAttributeName();

        $scheduledForDeletion = lcfirst($this->getRefFKPhpNameAffix($refFK, true)) . 'ScheduledForDeletion';

        $relationIdentifierSingular = $refFK->getIdentifierReversed();
        $relationIdentifierPlural = $refFK->getIdentifierReversed($this->getPluralizer());

        $script .= "
    /**
     * Method called to associate a $className object to this object
     * through the $className foreign key attribute.
     *
     * @param $className $modelVar
     *
     * @return \$this
     */
    public function add{$relationIdentifierSingular}($className $modelVar)
    {
        if (\$this->$attributeName === null) {
            \$this->init{$relationIdentifierPlural}();
            \$this->{$attributeName}Partial = true;
        }

        if (!\$this->{$attributeName}->contains($modelVar)) {
            \$this->doAdd{$relationIdentifierSingular}($modelVar);

            if (\$this->{$scheduledForDeletion} && \$this->{$scheduledForDeletion}->contains($modelVar)) {
                \$this->{$scheduledForDeletion}->remove(\$this->{$scheduledForDeletion}->search($modelVar));
            }
        }

        return \$this;
    }
";
    }

    /**
     * Adds the method that returns the size of the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addCount(string &$script): void
    {
        $refFK = $this->relation;
        $fkQueryClassName = $this->getClassNameFromBuilder($this->getNewStubQueryBuilder($refFK->getTable()));
        $relCol = $this->getRefFKPhpNameAffix($refFK, true);
        $collName = $this->getAttributeName();

        $joinedTableObjectBuilder = $this->getNewObjectBuilder($refFK->getTable());
        $className = $this->getClassNameFromBuilder($joinedTableObjectBuilder);

        $script .= "
    /**
     * Returns the number of related $className objects.
     *
     * @param Criteria|null \$criteria
     * @param bool \$distinct
     * @param ConnectionInterface|null \$con
     *
     * @return int Count of related $className objects.
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function count{$relCol}(?Criteria \$criteria = null, bool \$distinct = false, ?ConnectionInterface \$con = null): int
    {
        \$partial = \$this->{$collName}Partial && !\$this->isNew();
        if (\$this->$collName === null || \$criteria !== null || \$partial) {
            if (\$this->isNew() && \$this->$collName === null) {
                return 0;
            }

            if (\$partial && !\$criteria) {
                return count(\$this->get$relCol());
            }

            \$query = $fkQueryClassName::create(null, \$criteria);
            if (\$distinct) {
                \$query->distinct();
            }

            return \$query
                ->filterBy" . $refFK->getIdentifier() . "(\$this)
                ->count(\$con);
        }

        return count(\$this->$collName);
    }
";
    }

    /**
     * Adds the method that returns the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGet(string &$script): void
    {
        $refFK = $this->relation;
        $table = $refFK->getTable();
        $attributeName = $this->getAttributeName();

        $modelClassName = $this->getClassNameFromTable($table);
        $modelClassNameFqcn = $this->getClassNameFromBuilder($this->getNewStubObjectBuilder($table), true);
        $relationIdentifierPlural = $refFK->getIdentifierReversed($this->getPluralizer());
        $fkQueryClassName = $this->getClassNameFromBuilder($this->getNewStubQueryBuilder($table));
        [$collectionClassName, $collectionType] = $this->resolveObjectCollectionClassNameAndType($table);

        $script .= "
    /**
     * Gets an array of $modelClassName objects which contain a foreign key that references this object.
     *
     * If the \$criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without \$criteria, the cached collection is returned.
     * If this " . $this->ownClassIdentifier() . " is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria|null \$criteria
     * @param ConnectionInterface|null \$con
     *
     * @return $collectionType
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function get$relationIdentifierPlural(?Criteria \$criteria = null, ?ConnectionInterface \$con = null): $collectionClassName
    {
        \$partial = \$this->{$attributeName}Partial && !\$this->isNew();
        if (\$this->$attributeName && !\$criteria && !\$partial) {
            return \$this->$attributeName;
        }

        if (\$this->isNew()) {
            // return empty collection
            if (\$this->$attributeName === null) {
                \$this->init{$relationIdentifierPlural}();

                return \$this->$attributeName;
            }

            \$$attributeName = new $collectionClassName();
            \${$attributeName}->setModel('$modelClassNameFqcn');

            return \$$attributeName;
        }

        \$$attributeName = $fkQueryClassName::create(null, \$criteria)
            ->filterBy" . $refFK->getIdentifier() . "(\$this)
            ->find(\$con);

        if (\$criteria) {
            if (\$this->{$attributeName}Partial !== false && count(\$$attributeName)) {
                \$this->init{$relationIdentifierPlural}(false);

                foreach (\$$attributeName as \$obj) {
                    if (!\$this->{$attributeName}->contains(\$obj)) {
                        \$this->{$attributeName}->append(\$obj);
                    }
                }

                \$this->{$attributeName}Partial = true;
            }

            return \$$attributeName;
        }

        if (\$this->{$attributeName}Partial && \$this->$attributeName) {
            foreach (\$this->$attributeName as \$obj) {
                if (\$obj->isNew()) {
                    \${$attributeName}[] = \$obj;
                }
            }
        }

        \$this->$attributeName = \$$attributeName;
        \$this->{$attributeName}Partial = false;

        return \$this->$attributeName;
    }
";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addSet(string &$script): void
    {
        $refFK = $this->relation;
        $relatedName = $this->getRefFKPhpNameAffix($refFK, true);
        $relatedObjectClassName = $this->getRefFKPhpNameAffix($refFK, false);

        $className = $this->getClassNameFromTable($refFK->getTable());

        $inputCollectionVar = lcfirst($relatedName);
        $inputCollectionEntry = lcfirst($this->getRefFKPhpNameAffix($refFK, false));
        [$targetCollectionType, $_] = $this->resolveObjectCollectionClassNameAndType($refFK->getTable());
        $attributeName = $this->getAttributeName();
        $relCol = $refFK->getIdentifier();

        $script .= "
    /**
     * Sets a collection of $className objects related by a one-to-many relationship
     * to the current object.
     *
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param \Propel\Runtime\Collection\Collection<$className> \${$inputCollectionVar}
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con
     *
     * @return \$this
     */
    public function set{$relatedName}(Collection \${$inputCollectionVar}, ?ConnectionInterface \$con = null): static
    {
        \${$inputCollectionVar}ToDelete = \$this->get{$relatedName}(null, \$con)->diff(\${$inputCollectionVar});\n";

        if ($refFK->isAtLeastOneLocalPrimaryKey()) {
            $script .= "
        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        \$this->{$inputCollectionVar}ScheduledForDeletion = clone \${$inputCollectionVar}ToDelete;
";
        } else {
            $script .= "
        \$this->{$inputCollectionVar}ScheduledForDeletion = \${$inputCollectionVar}ToDelete;\n";
        }

        $script .= "
        foreach (\${$inputCollectionVar}ToDelete as \${$inputCollectionEntry}Removed) {
            \${$inputCollectionEntry}Removed->set{$relCol}(null);
        }

        \$this->{$attributeName} = null;
        foreach (\${$inputCollectionVar} as \${$inputCollectionEntry}) {
            \$this->add{$relatedObjectClassName}(\${$inputCollectionEntry});
        }

        \$this->{$attributeName}Partial = false;
        \$this->{$attributeName} = \${$inputCollectionVar} instanceof $targetCollectionType
            ? \${$inputCollectionVar} : new $targetCollectionType(\${$inputCollectionVar}->getData());

        return \$this;
    }
";
    }

    /**
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDoAdd(string &$script): void
    {
        $refFK = $this->relation;
        $tblFK = $refFK->getTable();

        $className = $this->getClassNameFromTable($refFK->getTable());

        if ($tblFK->getChildrenColumn()) {
            $className = $this->getClassNameFromTable($refFK->getTable());
        }

        $relatedObjectClassName = $this->getRefFKPhpNameAffix($refFK, false);
        $lowerRelatedObjectClassName = lcfirst($relatedObjectClassName);
        $collName = $this->getAttributeName();

        $script .= "
    /**
     * @param {$className} \${$lowerRelatedObjectClassName} The $className object to add.
     *
     * @return void
     */
    protected function doAdd{$relatedObjectClassName}($className \${$lowerRelatedObjectClassName}): void
    {
        \$this->{$collName}[]= \${$lowerRelatedObjectClassName};
        \${$lowerRelatedObjectClassName}->set" . $refFK->getIdentifier() . "(\$this);
    }
";
    }

    /**
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addRemove(string &$script): void
    {
        $refFK = $this->relation;

        $targetTable = $refFK->getTable();

        $className = $this->getClassNameFromTable($refFK->getTable());

        if ($targetTable->getChildrenColumn()) {
            $className = $this->getClassNameFromTable($refFK->getTable());
        }

        $relatedName = $this->getRefFKPhpNameAffix($refFK, true);
        $relatedObjectClassName = $this->getRefFKPhpNameAffix($refFK, false);
        $inputCollection = lcfirst($relatedName . 'ScheduledForDeletion');
        $lowerRelatedObjectClassName = lcfirst($relatedObjectClassName);

        $collName = $this->getAttributeName();
        $relCol = $refFK->getIdentifier();
        $localColumn = $refFK->getLocalColumn();

        $script .= "
    /**
     * @param {$className} \${$lowerRelatedObjectClassName} The $className object to remove.
     *
     * @return \$this
     */
    public function remove{$relatedObjectClassName}($className \${$lowerRelatedObjectClassName}): static
    {
        if (\$this->get{$relatedName}()->contains(\${$lowerRelatedObjectClassName})) {
            \$pos = \$this->{$collName}->search(\${$lowerRelatedObjectClassName});
            \$this->{$collName}->remove(\$pos);
            if (\$this->{$inputCollection} === null) {
                \$this->{$inputCollection} = clone \$this->{$collName};
                \$this->{$inputCollection}->clear();
            }";

        if (!$refFK->isComposite() && !$localColumn->isNotNull()) {
            $script .= "
            \$this->{$inputCollection}[]= \${$lowerRelatedObjectClassName};";
        } else {
            $script .= "
            \$this->{$inputCollection}[]= clone \${$lowerRelatedObjectClassName};";
        }

        $script .= "
            \${$lowerRelatedObjectClassName}->set{$relCol}(null);
        }

        return \$this;
    }
";
    }

    /**
     * Adds the method that clears the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addPartial(string &$script): void
    {
        $relCol = $this->relation->getIdentifierReversed($this->getPluralizer());
        $attributeName = $this->getAttributeName();

        $script .= "
    /**
     * Reset is the $attributeName collection loaded partially.
     *
     * @return void
     */
    public function resetPartial{$relCol}(\$isPartial = true): void
    {
        \$this->{$attributeName}Partial = \$isPartial;
    }
";
    }

    /**
     * Adds the method that fetches fkey-related (referencing) objects but also joins in data from another table.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGetJoinMethods(string &$script): void
    {
        $targetTable = $this->relation->getTable();
        $modelClassName = $targetTable->getPhpName();
        $joinBehavior = $this->getBuildProperty('generator.objectModel.useLeftJoinsInDoJoinMethods')
            ? 'Criteria::LEFT_JOIN' : 'Criteria::INNER_JOIN';

        $targetQueryClassName = $this->getClassNameFromBuilder($this->getNewStubQueryBuilder($targetTable));
        $relationIdentifierPlural = $this->relation->getIdentifierReversed($this->getPluralizer());
        $relationRelatedBySuffix = $this->relation->buildIdentifierRelatedBySuffix();
        [$collectionClassName, $collectionType] = $this->resolveObjectCollectionClassNameAndType($targetTable);

        foreach ($targetTable->getForeignKeys() as $relationAtTarget) {
            $tblFK2 = $relationAtTarget->getForeignTable();
            if ($tblFK2->isForReferenceOnly() || $this->relation === $relationAtTarget) {
                continue;
            }

            if ($relationRelatedBySuffix && $relationRelatedBySuffix === $relationAtTarget->buildIdentifierRelatedBySuffix()) {
                continue;
            }

            $currentRelationIdentifier = $relationAtTarget->getIdentifier();

            $script .= "
    /**
     * @deprecated do join yourself if you need it. 
     *
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this $modelClassName is new, it will return
     * an empty collection; or if this $modelClassName has previously
     * been saved, it will retrieve related $relationIdentifierPlural from storage.
     *
     * @param Criteria|null \$criteria
     * @param ConnectionInterface|null
     * @param string \$joinBehavior optional join type to use (defaults to $joinBehavior)
     *
     * @return $collectionType
     */
    public function get{$relationIdentifierPlural}Join{$currentRelationIdentifier}(?Criteria \$criteria = null, ?ConnectionInterface \$con = null, \$joinBehavior = $joinBehavior): $collectionClassName
    {";
                $script .= "
        \$query = $targetQueryClassName::create(null, \$criteria);
        \$query->joinWith('$currentRelationIdentifier', \$joinBehavior);

        return \$this->get{$relationIdentifierPlural}(\$query, \$con);
    }
";
        }
    }
}
