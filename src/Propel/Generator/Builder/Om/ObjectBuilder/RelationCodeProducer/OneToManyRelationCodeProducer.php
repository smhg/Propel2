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
     * @param \Propel\Generator\Model\ForeignKey $refFK
     *
     * @return void
     */
    public function addAttributes(string &$script): void
    {
        $refFK = $this->relation;
        $className = $this->getClassNameFromTable($refFK->getTable());
        $variable = '$' . $this->getAttributeName();

        $script .= "
    /**
     * @var ObjectCollection<{$className}>|null Collection to store aggregation of $className objects.
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
        $varName = $this->getAttributeName();
        $script .= "
        if (\$this->$varName) {
            foreach (\$this->$varName as \$o) {
                \$o->clearAllReferences(\$deep);
            }
        }";

        return $varName;
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
        $refFK = $this->relation;
        $relCol = $this->getRefFKPhpNameAffix($refFK, true);
        $collName = $this->getAttributeName();

        $script .= "
    /**
     * Initializes the $collName collection.
     *
     * By default this just sets the $collName collection to an empty array (like clear$collName());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param bool \$overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function init$relCol(bool \$overrideExisting = true): void
    {
        if (\$this->$collName  !== null&& !\$overrideExisting) {
            return;
        }

        \$collectionClassName = " . $this->getClassNameFromBuilder($this->getNewTableMapBuilder($refFK->getTable())) . "::getTableMap()->getCollectionClassName();

        \$this->{$collName} = new \$collectionClassName;
        \$this->{$collName}->setModel('" . $this->getClassNameFromBuilder($this->getNewStubObjectBuilder($refFK->getTable()), true) . "');
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
        $refFK = $this->relation;
        $relCol = $this->getRefFKPhpNameAffix($refFK, true);
        $collName = $this->getAttributeName();

        $script .= "
    /**
     * Clears out the $collName collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return \$this
     * @see add$relCol()
     */
    public function clear$relCol()
    {
        \$this->$collName = null; // important to set this to NULL since that means it is uninitialized

        return \$this;
    }
";
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
            $className = $this->getClassNameFromTable($refFK->getTable());
        }

        $collName = $this->getAttributeName();

        $scheduledForDeletion = lcfirst($this->getRefFKPhpNameAffix($refFK, true)) . 'ScheduledForDeletion';

        $script .= "
    /**
     * Method called to associate a $className object to this object
     * through the $className foreign key attribute.
     *
     * @param $className \$l $className
     * @return \$this The current object (for fluent API support)
     */
    public function add" . $this->getRefFKPhpNameAffix($refFK, false) . "($className \$l)
    {
        if (\$this->$collName === null) {
            \$this->init" . $this->getRefFKPhpNameAffix($refFK, true) . "();
            \$this->{$collName}Partial = true;
        }

        if (!\$this->{$collName}->contains(\$l)) {
            \$this->doAdd" . $this->getRefFKPhpNameAffix($refFK, false) . "(\$l);

            if (\$this->{$scheduledForDeletion} and \$this->{$scheduledForDeletion}->contains(\$l)) {
                \$this->{$scheduledForDeletion}->remove(\$this->{$scheduledForDeletion}->search(\$l));
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
     * @param Criteria \$criteria
     * @param bool \$distinct
     * @param ConnectionInterface \$con
     * @return int Count of related $className objects.
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
        $fkQueryClassName = $this->getClassNameFromBuilder($this->getNewStubQueryBuilder($refFK->getTable()));
        $relCol = $this->getRefFKPhpNameAffix($refFK, true);
        $collName = $this->getAttributeName();

        $className = $this->getClassNameFromTable($refFK->getTable());

        $script .= "
    /**
     * Gets an array of $className objects which contain a foreign key that references this object.
     *
     * If the \$criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without \$criteria, the cached collection is returned.
     * If this " . $this->ownClassIdentifier() . " is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria \$criteria optional Criteria object to narrow the query
     * @param ConnectionInterface \$con optional connection object
     * @return ObjectCollection|{$className}[] List of $className objects
     * @phpstan-return ObjectCollection&\Traversable<{$className}> List of $className objects
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function get$relCol(?Criteria \$criteria = null, ?ConnectionInterface \$con = null)
    {
        \$partial = \$this->{$collName}Partial && !\$this->isNew();
        if (\$this->$collName === null|| \$criteria !== null|| \$partial) {
            if (\$this->isNew()) {
                // return empty collection
                if (\$this->$collName === null) {
                    \$this->init" . $this->getRefFKPhpNameAffix($refFK, true) . "();
                } else {
                    \$collectionClassName = " . $this->getClassNameFromBuilder($this->getNewTableMapBuilder($refFK->getTable())) . "::getTableMap()->getCollectionClassName();

                    \$$collName = new \$collectionClassName;
                    \${$collName}->setModel('" . $this->getClassNameFromBuilder($this->getNewStubObjectBuilder($refFK->getTable()), true) . "');

                    return \$$collName;
                }
            } else {
                \$$collName = $fkQueryClassName::create(null, \$criteria)
                    ->filterBy" . $refFK->getIdentifier() . "(\$this)
                    ->find(\$con);

                if (\$criteria !== null) {
                    if (false !== \$this->{$collName}Partial && count(\$$collName)) {
                        \$this->init" . $this->getRefFKPhpNameAffix($refFK, true) . "(false);

                        foreach (\$$collName as \$obj) {
                            if (false == \$this->{$collName}->contains(\$obj)) {
                                \$this->{$collName}->append(\$obj);
                            }
                        }

                        \$this->{$collName}Partial = true;
                    }

                    return \$$collName;
                }

                if (\$partial && \$this->$collName) {
                    foreach (\$this->$collName as \$obj) {
                        if (\$obj->isNew()) {
                            \${$collName}[] = \$obj;
                        }
                    }
                }

                \$this->$collName = \$$collName;
                \$this->{$collName}Partial = false;
            }
        }

        return \$this->$collName;
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

        $inputCollection = lcfirst($relatedName);
        $inputCollectionEntry = lcfirst($this->getRefFKPhpNameAffix($refFK, false));

        $collName = $this->getAttributeName();
        $relCol = $refFK->getIdentifier();

        $script .= "
    /**
     * Sets a collection of $className objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param Collection \${$inputCollection} A Propel collection.
     * @param ConnectionInterface \$con Optional connection object
     * @return \$this The current object (for fluent API support)
     */
    public function set{$relatedName}(Collection \${$inputCollection}, ?ConnectionInterface \$con = null)
    {
        /** @var {$className}[] \${$inputCollection}ToDelete */
        \${$inputCollection}ToDelete = \$this->get{$relatedName}(new Criteria(), \$con)->diff(\${$inputCollection});

        ";

        if ($refFK->isAtLeastOneLocalPrimaryKey()) {
            $script .= "
        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        \$this->{$inputCollection}ScheduledForDeletion = clone \${$inputCollection}ToDelete;
";
        } else {
            $script .= "
        \$this->{$inputCollection}ScheduledForDeletion = \${$inputCollection}ToDelete;
";
        }

        $script .= "
        foreach (\${$inputCollection}ToDelete as \${$inputCollectionEntry}Removed) {
            \${$inputCollectionEntry}Removed->set{$relCol}(null);
        }

        \$this->{$collName} = null;
        foreach (\${$inputCollection} as \${$inputCollectionEntry}) {
            \$this->add{$relatedObjectClassName}(\${$inputCollectionEntry});
        }

        \$this->{$collName} = \${$inputCollection};
        \$this->{$collName}Partial = false;

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
     * @param \Propel\Generator\Model\ForeignKey $refFK
     *
     * @return void
     */
    protected function addRemove(string &$script): void
    {
        $refFK = $this->relation;

        $tblFK = $refFK->getTable();

        $className = $this->getClassNameFromTable($refFK->getTable());

        if ($tblFK->getChildrenColumn()) {
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
     * @return \$this The current object (for fluent API support)
     */
    public function remove{$relatedObjectClassName}($className \${$lowerRelatedObjectClassName})
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
        $refFK = $this->relation;
        $relCol = $this->getRefFKPhpNameAffix($refFK, true);
        $collName = $this->getAttributeName();

        $script .= "
    /**
     * Reset is the $collName collection loaded partially.
     *
     * @return void
     */
    public function resetPartial{$relCol}(\$v = true): void
    {
        \$this->{$collName}Partial = \$v;
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
        $refFK = $this->relation;
        $table = $this->getTable();
        $tblFK = $refFK->getTable();
        $joinBehavior = $this->getBuildProperty('generator.objectModel.useLeftJoinsInDoJoinMethods') ? 'Criteria::LEFT_JOIN' : 'Criteria::INNER_JOIN';

        $fkQueryClassName = $this->getClassNameFromBuilder($this->getNewStubQueryBuilder($refFK->getTable()));
        $relCol = $this->getRefFKPhpNameAffix($refFK, true);

        $className = $this->getClassNameFromTable($tblFK);

        foreach ($tblFK->getForeignKeys() as $fk2) {
            $tblFK2 = $fk2->getForeignTable();
            $doJoinGet = !$tblFK2->isForReferenceOnly();

            // it doesn't make sense to join in rows from the current table, since we are fetching
            // objects related to *this* table (i.e. the joined rows will all be the same row as current object)
            if ($this->getTable()->getPhpName() == $tblFK2->getPhpName()) {
                $doJoinGet = false;
            }

            $relCol2 = $fk2->getIdentifier();

            if (
                $refFK->buildIdentifierRelatedBySuffix() != '' &&
                ($refFK->buildIdentifierRelatedBySuffix() == $fk2->buildIdentifierRelatedBySuffix())
            ) {
                $doJoinGet = false;
            }

            if ($doJoinGet) {
                $script .= "

    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this " . $table->getPhpName() . " is new, it will return
     * an empty collection; or if this " . $table->getPhpName() . " has previously
     * been saved, it will retrieve related $relCol from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in " . $table->getPhpName() . ".
     *
     * @param Criteria \$criteria optional Criteria object to narrow the query
     * @param ConnectionInterface \$con optional connection object
     * @param string \$joinBehavior optional join type to use (defaults to $joinBehavior)
     * @return ObjectCollection|{$className}[] List of $className objects
     * @phpstan-return ObjectCollection&\Traversable<$className}> List of $className objects
     */
    public function get" . $relCol . 'Join' . $relCol2 . "(?Criteria \$criteria = null, ?ConnectionInterface \$con = null, \$joinBehavior = $joinBehavior)
    {";
                $script .= "
        \$query = $fkQueryClassName::create(null, \$criteria);
        \$query->joinWith('" . $fk2->getIdentifier() . "', \$joinBehavior);

        return \$this->get" . $relCol . "(\$query, \$con);
    }
";
            } /* end if ($doJoinGet) */
        } /* end foreach ($tblFK->getForeignKeys() as $fk2) { */
    }
}
