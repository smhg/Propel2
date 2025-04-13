<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder;

use Propel\Generator\Builder\DataModelBuilder;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Model\CrossForeignKeys;
use Propel\Generator\Model\ForeignKey;

/**
 * Generates a database loader file, which is used to register all table maps with the DatabaseMap.
 */
class CrossFkCodeProducer extends DataModelBuilder
{
    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    protected function resolveSourceTableClassName(ForeignKey $fk): string
    {
        return $this->referencedClasses->getInternalNameOfTable($fk->getTable());
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    protected function resolveTargetTableClassName(ForeignKey $fk): string
    {
        return $this->referencedClasses->getInternalNameOfTable($fk->getForeignTableOrFail());
    }

    /**
     * @return void
     */
    public function registerTargetClasses(): void
    {
        foreach ($this->getTable()->getCrossFks() as $crossFKs) {
            foreach ($crossFKs->getCrossForeignKeys() as $fk) {
                $table = $fk->getForeignTable();
                $this->referencedClasses->registerBuilderResultClass($this->getNewStubObjectBuilder($table), 'Child');
                $this->referencedClasses->registerBuilderResultClass($this->getNewStubQueryBuilder($table));
            }
        }
    }

    /**
     * @param string $script
     *
     * @return void
     */
    public function addCrossFKMethods(string &$script): void
    {
        $this->registerTargetClasses();

        foreach ($this->getTable()->getCrossFks() as $crossFKs) {
            $this->addCrossFKClear($script, $crossFKs);
            $this->addCrossFKInit($script, $crossFKs);
            $this->addCrossFKisLoaded($script, $crossFKs);
            $this->addCrossFKCreateQuery($script, $crossFKs);
            $this->addCrossFKGet($script, $crossFKs);
            $this->addCrossFKSet($script, $crossFKs);
            $this->addCrossFKCount($script, $crossFKs);
            $this->addCrossFKAdd($script, $crossFKs);
            $this->buildDoAdd($script, $crossFKs);
            $this->addCrossFKRemove($script, $crossFKs);
            //$this->addCrossFKRemoves($script, $crossFKs);
        }
    }

    /**
     * Resolve name of cross relation from perspective of current table (in contrast to back-relation
     * from target table or regular fk-relation on middle table).
     * 
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     * @param bool $plural
     * @param bool $lowercased
     *
     * @return string
     */
    protected function resolveRelationForwardName(CrossForeignKeys $crossFKs, bool $plural = true, bool $lowercased = false): string
    {
        $relationName = $this->buildCombineCrossFKsPhpNameAffix($crossFKs, false);

        $existingTable = $this->getDatabase()->getTableByPhpName($relationName);
        $isNameCollision = $existingTable && $this->getTable()->isConnectedWithTable($existingTable);
        if ($plural || $isNameCollision) {
            $relationName = $this->buildCombineCrossFKsPhpNameAffix($crossFKs, $plural, $isNameCollision);
        }

        return $lowercased ? lcfirst($relationName) : $relationName;
    }

    /**
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     * @param \Propel\Generator\Model\ForeignKey|array|null $crossFK will be the first variable defined
     *
     * @return array<string>
     */
    protected function getCrossFKAddMethodInformation(CrossForeignKeys $crossFKs, $crossFK = null): array
    {
        $signature = $shortSignature = $normalizedShortSignature = $phpDoc = [];
        if ($crossFK instanceof ForeignKey) {
            $crossObjectName = '$' . $this->nameProducer->resolveRelationForwardName($crossFK, false, true);
            $crossObjectClassName = $this->resolveTargetTableClassName($crossFK);
            $signature[] = "$crossObjectClassName $crossObjectName" . ($crossFK->isAtLeastOneLocalColumnRequired() ? '' : ' = null');
            $shortSignature[] = $crossObjectName;
            $normalizedShortSignature[] = $crossObjectName;
            $phpDoc[] = "
     * @param $crossObjectClassName $crossObjectName";
        } elseif ($crossFK == null) {
            $crossFK = [];
        }

        $this->extractCrossInformation($crossFKs, $crossFK, $signature, $shortSignature, $normalizedShortSignature, $phpDoc);

        $signature = implode(', ', $signature);
        $shortSignature = implode(', ', $shortSignature);
        $normalizedShortSignature = implode(', ', $normalizedShortSignature);
        $phpDoc = implode(', ', $phpDoc);

        return [$signature, $shortSignature, $normalizedShortSignature, $phpDoc];
    }

    /**
     * Extracts some useful information from a CrossForeignKeys object.
     *
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     * @param \Propel\Generator\Model\ForeignKey|array $crossFKToIgnore
     * @param array $signature
     * @param array $shortSignature
     * @param array $normalizedShortSignature
     * @param array $phpDoc
     *
     * @return void
     */
    protected function extractCrossInformation(
        CrossForeignKeys $crossFKs,
        $crossFKToIgnore,
        array &$signature,
        array &$shortSignature,
        array &$normalizedShortSignature,
        array &$phpDoc
    ): void {
        foreach ($crossFKs->getCrossForeignKeys() as $fk) {
            if (is_array($crossFKToIgnore) && in_array($fk, $crossFKToIgnore)) {
                continue;
            } elseif ($fk === $crossFKToIgnore) {
                continue;
            }

            $phpType = $typeHint = $this->resolveTargetTableClassName($fk);
            $name = '$' . $this->nameProducer->resolveRelationForwardName($fk, false, true);

            $normalizedShortSignature[] = $name;

            $signature[] = ($typeHint ? "$typeHint " : '') . $name;
            $shortSignature[] = $name;
            $phpDoc[] = "
     * @param $phpType $name";
        }

        foreach ($crossFKs->getUnclassifiedPrimaryKeys() as $primaryKey) {
            //we need to add all those $primaryKey s as additional parameter as they are needed
            //to create the entry in the middle-table.
            $defaultValue = $primaryKey->getDefaultValueString();

            $phpType = $primaryKey->getPhpType();
            $typeHint = $primaryKey->isPhpArrayType() ? 'array' : '';
            $name = '$' . lcfirst($primaryKey->getPhpName());

            $normalizedShortSignature[] = $name;
            $signature[] = ($typeHint ? "$typeHint " : '') . $name . ($defaultValue !== 'null' ? " = $defaultValue" : '');
            $shortSignature[] = $name;
            $phpDoc[] = "
     * @param $phpType $name";
        }
    }

    /**
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     * @param bool $plural
     * @param bool $withPrefix
     *
     * @return string
     */
    protected function buildCombineCrossFKsPhpNameAffix(CrossForeignKeys $crossFKs, bool $plural = true, bool $withPrefix = false): string
    {
        $names = [];
        if ($withPrefix) {
            $names[] = 'Cross';
        }
        $fks = $crossFKs->getCrossForeignKeys();
        $lastCrossFk = array_pop($fks);
        $unclassifiedPrimaryKeys = $crossFKs->getUnclassifiedPrimaryKeys();
        $lastIsPlural = $plural && !$unclassifiedPrimaryKeys;

        foreach ($fks as $fk) {
            $names[] = $this->nameProducer->resolveRelationForwardName($fk, false);
        }
        $names[] = $this->nameProducer->resolveRelationForwardName($lastCrossFk, $lastIsPlural);

        if (!$unclassifiedPrimaryKeys) {
            return implode('', $names);
        }

        foreach ($unclassifiedPrimaryKeys as $pk) {
            $names[] = $pk->getPhpName();
        }

        $name = implode('', $names);

        return $plural === true ? $this->getPluralizer()->getPluralForm($name) : $name;
    }

    /**
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     * @param \Propel\Generator\Model\ForeignKey $excludeFK
     *
     * @return string
     */
    protected function getCrossRefFKGetterName(CrossForeignKeys $crossFKs, ForeignKey $excludeFK): string
    {
        $names = [];

        $fks = $crossFKs->getCrossForeignKeys();

        foreach ($crossFKs->getMiddleTable()->getForeignKeys() as $fk) {
            if ($fk !== $excludeFK && ($fk === $crossFKs->getIncomingForeignKey() || in_array($fk, $fks))) {
                $names[] = $this->nameProducer->resolveRelationForwardName($fk, false);
            }
        }

        foreach ($crossFKs->getUnclassifiedPrimaryKeys() as $pk) {
            $names[] = $pk->getPhpName();
        }

        $name = implode('', $names);

        return $this->getPluralizer()->getPluralForm($name);
    }

    /**
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return array
     */
    protected function getCrossFKInformation(CrossForeignKeys $crossFKs): array
    {
        $names = [];
        $signatures = [];
        $shortSignature = [];
        $phpDoc = [];

        foreach ($crossFKs->getCrossForeignKeys() as $fk) {
            $crossObjectName = '$' . $this->nameProducer->resolveRelationForwardName($fk, false, true);
            $crossObjectClassName = $this->builderFactory->createObjectBuilder($fk->getForeignTableOrFail())->resolveInternalNameOfStubObject();

            $names[] = $crossObjectClassName;
            $signatures[] = "$crossObjectClassName $crossObjectName" . ($fk->isAtLeastOneLocalColumnRequired() ? '' : ' = null');
            $shortSignature[] = $crossObjectName;
            $phpDoc[] = "
     * @param $crossObjectClassName $crossObjectName The object to relate";
        }

        $names = implode(', ', $names) . (1 < count($names) ? ' combination' : '');
        $phpDoc = implode('', $phpDoc);
        $signatures = implode(', ', $signatures);
        $shortSignature = implode(', ', $shortSignature);

        return [
            $names,
            $phpDoc,
            $signatures,
            $shortSignature,
        ];
    }

    /**
     * Adds the method that clears the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    protected function addCrossFKClear(string &$script, CrossForeignKeys $crossFKs): void
    {
        $relCol = $this->resolveRelationForwardName($crossFKs);
        $collName = $this->buildLocalColumnNameForCrossRef($crossFKs, false);

        $script .= "
    /**
     * Clears out the {$collName} collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see static::add{$relCol}()
     */
    public function clear{$relCol}()
    {
        \$this->$collName = null; // important to set this to NULL since that means it is uninitialized
    }
";
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    public function addCrossScheduledForDeletionAttribute(string &$script, CrossForeignKeys $crossFKs): void
    {
        $script .= $crossFKs->hasCombinedKey() 
            ? $this->buildScheduledForDeletionAttributeWithCombinedKey($crossFKs)
            : $this->buildScheduledForDeletionAttributeWithSimpleKey($crossFKs);
    }

    /**
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     * @return string
     */
    protected function buildScheduledForDeletionAttributeWithSimpleKey(CrossForeignKeys $crossFKs): string
    {
        $refFK = $crossFKs->getIncomingForeignKey();
        if ($refFK->isLocalPrimaryKey()) {
            return '';
        }
        $name = $this->getCrossScheduledForDeletionVarName($crossFKs);
        $className = $this->resolveTargetTableClassName($crossFKs->getCrossForeignKeys()[0]);

        return "
    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection|{$className}[]
     * @phpstan-var ObjectCollection&\Traversable<{$className}>
     */
    protected \$$name = null;\n";
    }

    /**
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     * @return string
     */
    protected function buildScheduledForDeletionAttributeWithCombinedKey(CrossForeignKeys $crossFKs): string
    {
        $name = $this->getCrossScheduledForDeletionVarName($crossFKs);
        [$names] = $this->getCrossFKInformation($crossFKs);

        return "
    /**
     * @var ObjectCombinationCollection Cross CombinationCollection to store aggregation of $names combinations.
     */
    protected \$$name = null;\n";
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $crossFK
     *
     * @return string
     */
    protected function getCrossFKVarName(ForeignKey $crossFK): string
    {
        return 'coll' . $this->nameProducer->resolveRelationForwardName($crossFK, true);
    }

    /**
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     * @param bool $uppercaseFirstChar
     *
     * @return string
     */
    public function buildLocalColumnNameForCrossRef(CrossForeignKeys $crossFKs, bool $uppercaseFirstChar): string
    {
        $columnName = 'coll' . $this->resolveRelationForwardName($crossFKs);

        return $uppercaseFirstChar ? ucfirst($columnName) : $columnName;
    }

    /**
     * Adds the method that initializes the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    protected function addCrossFKInit(string &$script, CrossForeignKeys $crossFKs): void
    {
        if ($crossFKs->hasCombinedKey()) {

            $columnName = 'combination' . $this->buildLocalColumnNameForCrossRef($crossFKs, true);
            $relationName = $this->resolveRelationForwardName($crossFKs, true);
            $collectionClassName = 'ObjectCombinationCollection';

            $this->buildInitCode($script, $columnName, $relationName, $collectionClassName, null, null);
        } else {
            foreach ($crossFKs->getCrossForeignKeys() as $fk) {
                $relationName = $this->nameProducer->resolveRelationForwardName($fk, true);
                $columnName = $this->getCrossFKVarName($fk);
                $relatedObjectClassName = $this->referencedClasses->getInternalNameOfBuilderResultClass(
                    $this->getNewStubObjectBuilder($fk->getForeignTable()),
                    true,
                );

                $foreignTableMapName = $this->resolveClassNameForTable(GeneratorConfig::KEY_TABLEMAP, $fk->getTable());

                $this->buildInitCode($script, $columnName, $relationName, null, $foreignTableMapName, $relatedObjectClassName);
            }
        }
    }

    protected function buildInitCode(
        string &$script, 
        string $columnName, 
        string $relationName, 
        ?string $collectionClass, 
        ?string $foreignTableMapName, 
        ?string $relatedObjectClassName
    ): void {
        $script .= "
    /**
     * Initializes the $columnName crossRef collection.
     *
     * By default this just sets the $columnName collection to an empty collection (like clear$relationName());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function init$relationName()
    {";
            if ($collectionClass) {
                $script .= "
        \$this->$columnName = new $collectionClass;";
            } else {
                $script .= "
        \$collectionClassName = $foreignTableMapName::getTableMap()->getCollectionClassName();

        \$this->$columnName = new \$collectionClassName;";
            }

            $script .= "
        \$this->{$columnName}Partial = true;";
            if ($relatedObjectClassName) {
                $script .= "
        \$this->{$columnName}->setModel('$relatedObjectClassName');";
            }
            $script .= "
    }
";
    }

    /**
     * Adds the method that check if the referrer fkey collection is initialized.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    protected function addCrossFKIsLoaded(string &$script, CrossForeignKeys $crossFKs): void
    {
        $inits = [];

        if ($crossFKs->hasCombinedKey()) {
            $inits[] = [
                'relCol' => $this->resolveRelationForwardName($crossFKs, true),
                'collName' => 'combination' . $this->buildLocalColumnNameForCrossRef($crossFKs, true),
            ];
        } else {
            foreach ($crossFKs->getCrossForeignKeys() as $crossFK) {
                $relCol = $this->nameProducer->resolveRelationForwardName($crossFK, true);
                $collName = $this->getCrossFKVarName($crossFK);

                $inits[] = [
                    'relCol' => $relCol,
                    'collName' => $collName,
                ];
            }
        }

        foreach ($inits as $init) {
            $relCol = $init['relCol'];
            $collName = $init['collName'];

            $script .= "
    /**
     * Checks if the $collName collection is loaded.
     *
     * @return bool
     */
    public function is{$relCol}Loaded(): bool
    {
        return \$this->$collName !== null;
    }
";
        }
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    protected function addCrossFKCreateQuery(string &$script, CrossForeignKeys $crossFKs): void
    {
        if (!$crossFKs->hasCombinedKey()) {
            return;
        }

        $refFK = $crossFKs->getIncomingForeignKey();
        $selfRelationName = $this->nameProducer->resolveRelationForwardName($refFK, false);
        $firstFK = $crossFKs->getCrossForeignKeys()[0];
        $firstFkName = $this->nameProducer->resolveRelationForwardName($firstFK, true);

        $relatedQueryClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_QUERY_STUB, $firstFK->getForeignTable());
        $signature = $shortSignature = $normalizedShortSignature = $phpDoc = [];
        $this->extractCrossInformation($crossFKs, [$firstFK], $signature, $shortSignature, $normalizedShortSignature, $phpDoc);

        $signature = array_map(function ($item) {
            return $item . ' = null';
        }, $signature);
        $signature = implode(', ', $signature);
        $phpDoc = implode(', ', $phpDoc);

        $relatedUseQueryClassName = $this->getNewStubQueryBuilder($crossFKs->getMiddleTable())->getUnqualifiedClassName();
        $relatedUseQueryGetter = 'use' . ucfirst($relatedUseQueryClassName);
        $relatedUseQueryVariableName = lcfirst($relatedUseQueryClassName);

        $script .= "
    /**
     * Returns a new query object pre configured with filters from current object and given arguments to query the database.
     * $phpDoc
     * @param Criteria \$criteria
     *
     * @return $relatedQueryClassName
     */
    public function create{$firstFkName}Query($signature, ?Criteria \$criteria = null)
    {
        \$criteria = $relatedQueryClassName::create(\$criteria)
            ->filterBy{$selfRelationName}(\$this);

        \$$relatedUseQueryVariableName = \$criteria->{$relatedUseQueryGetter}();
";

        foreach ($crossFKs->getCrossForeignKeys() as $fk) {
            if ($crossFKs->getIncomingForeignKey() === $fk || $firstFK === $fk) {
                continue;
            }

            $filterName = $fk->getPhpName();
            $name = lcfirst($fk->getPhpName());

            $script .= "
        if (\$$name !== null) {
            \${$relatedUseQueryVariableName}->filterBy{$filterName}(\$$name);
        }
            ";
        }
        foreach ($crossFKs->getUnclassifiedPrimaryKeys() as $pk) {
            $filterName = $pk->getPhpName();
            $name = lcfirst($pk->getPhpName());

            $script .= "
        if (\$$name !== null) {
            \${$relatedUseQueryVariableName}->filterBy{$filterName}(\$$name);
        }
            ";
        }

        $script .= "
        \${$relatedUseQueryVariableName}->endUse();

        return \$criteria;
    }
";
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    protected function addCrossFKGet(string &$script, CrossForeignKeys $crossFKs): void
    {
        $refFK = $crossFKs->getIncomingForeignKey();
        $selfRelationName = $this->nameProducer->resolveRelationForwardName($refFK, false);
        $crossRefTableName = $crossFKs->getMiddleTable()->getName();

        if ($crossFKs->hasCombinedKey()) {
            $relatedName = $this->resolveRelationForwardName($crossFKs, true);
            $collVarName = 'combination' . $this->buildLocalColumnNameForCrossRef($crossFKs, true);

            $classNames = [];
            foreach ($crossFKs->getCrossForeignKeys() as $crossFK) {
                $classNames[] = $this->resolveClassNameForTable(GeneratorConfig::KEY_OBJECT_STUB, $crossFK->getForeignTable());
            }
            $classNames = implode(', ', $classNames);
            $relatedQueryClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_QUERY_STUB, $crossFKs->getMiddleTable());

            $script .= "
    /**
     * Gets a combined collection of $classNames objects related by a many-to-many relationship
     * to the current object by way of the $crossRefTableName cross-reference table.
     *
     * If the \$criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without \$criteria, the cached collection is returned.
     * If this " . $this->resolveInternalNameOfStubObject() . " is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria \$criteria Optional query object to filter the query
     * @param ConnectionInterface \$con Optional connection object
     *
     * @return ObjectCombinationCollection Combination list of {$classNames} objects
     */
    public function get{$relatedName}(?Criteria \$criteria = null, ?ConnectionInterface \$con = null)
    {
        \$partial = \$this->{$collVarName}Partial && !\$this->isNew();
        if (\$this->$collVarName === null|| \$criteria !== null || \$partial) {
            if (\$this->isNew()) {
                // return empty collection
                if (\$this->$collVarName === null) {
                    \$this->init{$relatedName}();
                }
            } else {

                \$query = $relatedQueryClassName::create(null, \$criteria)
                    ->filterBy{$selfRelationName}(\$this)";
            foreach ($crossFKs->getCrossForeignKeys() as $fk) {
                $varName = $this->nameProducer->resolveRelationForwardName($fk, false);
                $script .= "
                    ->join{$varName}()";
            }

            $script .= "
                ;

                \$items = \$query->find(\$con);
                \$$collVarName = new ObjectCombinationCollection();
                foreach (\$items as \$item) {
                    \$combination = [];
";

            foreach ($crossFKs->getCrossForeignKeys() as $fk) {
                $varName = $this->nameProducer->resolveRelationForwardName($fk, false);
                $script .= "
                    \$combination[] = \$item->get{$varName}();";
            }

            foreach ($crossFKs->getUnclassifiedPrimaryKeys() as $pk) {
                $varName = $pk->getPhpName();
                $script .= "
                    \$combination[] = \$item->get{$varName}();";
            }

            $script .= "
                    \${$collVarName}[] = \$combination;
                }

                if (\$criteria !== null) {
                    return \$$collVarName;
                }

                if (\$partial && \$this->{$collVarName}) {
                    //make sure that already added objects gets added to the list of the database.
                    foreach (\$this->{$collVarName} as \$obj) {
                        if (!\${$collVarName}->contains(...\$obj)) {
                            \${$collVarName}[] = \$obj;
                        }
                    }
                }

                \$this->$collVarName = \$$collVarName;
                \$this->{$collVarName}Partial = false;
            }
        }

        return \$this->$collVarName;
    }
";

            $relatedName = $this->resolveRelationForwardName($crossFKs, true);
            $firstFK = $crossFKs->getCrossForeignKeys()[0];
            $firstFkName = $this->nameProducer->resolveRelationForwardName($firstFK, true);

            $relatedObjectClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_OBJECT_STUB, $firstFK->getForeignTable());
            $signature = $shortSignature = $normalizedShortSignature = $phpDoc = [];
            $this->extractCrossInformation($crossFKs, [$firstFK], $signature, $shortSignature, $normalizedShortSignature, $phpDoc);

            $signature = array_map(function ($item) {
                return $item . ' = null';
            }, $signature);
            $signature = implode(', ', $signature);
            $phpDoc = implode(', ', $phpDoc);
            $shortSignature = implode(', ', $shortSignature);

            $script .= "
    /**
     * Returns a not cached ObjectCollection of $relatedObjectClassName objects. This will hit always the databases.
     * If you have attached new $relatedObjectClassName object to this object you need to call `save` first to get
     * the correct return value. Use get$relatedName() to get the current internal state.
     * $phpDoc
     * @param Criteria \$criteria
     * @param ConnectionInterface \$con
     *
     * @return {$relatedObjectClassName}[]|ObjectCollection
     * @phpstan-return ObjectCollection&\Traversable<{$relatedObjectClassName}>
     */
    public function get{$firstFkName}($signature, ?Criteria \$criteria = null, ?ConnectionInterface \$con = null)
    {
        return \$this->create{$firstFkName}Query($shortSignature, \$criteria)->find(\$con);
    }
";

            return;
        }

        foreach ($crossFKs->getCrossForeignKeys() as $crossFK) {
            $relatedName = $this->nameProducer->resolveRelationForwardName($crossFK, true);
            $relatedObjectClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_OBJECT_STUB, $crossFK->getForeignTable());
            $relatedQueryClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_QUERY_STUB, $crossFK->getForeignTable());

            $collName = $this->getCrossFKVarName($crossFK);

            $script .= "
    /**
     * Gets a collection of $relatedObjectClassName objects related by a many-to-many relationship
     * to the current object by way of the $crossRefTableName cross-reference table.
     *
     * If the \$criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without \$criteria, the cached collection is returned.
     * If this " . $this->resolveInternalNameOfStubObject() . " is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param Criteria \$criteria Optional query object to filter the query
     * @param ConnectionInterface \$con Optional connection object
     *
     * @return ObjectCollection|{$relatedObjectClassName}[] List of {$relatedObjectClassName} objects
     * @phpstan-return ObjectCollection&\Traversable<{$relatedObjectClassName}> List of {$relatedObjectClassName} objects
     */
    public function get{$relatedName}(?Criteria \$criteria = null, ?ConnectionInterface \$con = null)
    {
        \$partial = \$this->{$collName}Partial && !\$this->isNew();
        if (\$this->$collName === null || \$criteria !== null || \$partial) {
            if (\$this->isNew()) {
                // return empty collection
                if (\$this->$collName === null) {
                    \$this->init{$relatedName}();
                }
            } else {

                \$query = $relatedQueryClassName::create(null, \$criteria)
                    ->filterBy{$selfRelationName}(\$this);
                \$$collName = \$query->find(\$con);
                if (\$criteria !== null) {
                    return \$$collName;
                }

                if (\$partial && \$this->{$collName}) {
                    //make sure that already added objects gets added to the list of the database.
                    foreach (\$this->{$collName} as \$obj) {
                        if (!\${$collName}->contains(\$obj)) {
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
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    protected function addCrossFKSet(string &$script, CrossForeignKeys $crossFKs): void
    {
        $scheduledForDeletionVarName = $this->getCrossScheduledForDeletionVarName($crossFKs);

        $multi = $crossFKs->hasCombinedKey();

        $relatedNamePlural = $this->resolveRelationForwardName($crossFKs, true);
        $relatedName = $this->resolveRelationForwardName($crossFKs, false);
        $inputCollection = lcfirst($relatedNamePlural);
        $foreachItem = lcfirst($relatedName);
        $crossRefTableName = $crossFKs->getMiddleTable()->getName();

        if ($multi) {
            [$relatedObjectClassName] = $this->getCrossFKInformation($crossFKs);
            $collName = 'combination' . $this->buildLocalColumnNameForCrossRef($crossFKs, true);
        } else {
            $crossFK = $crossFKs->getCrossForeignKeys()[0];
            $relatedObjectClassName = $this->getNewStubObjectBuilder($crossFK->getForeignTable())->getUnqualifiedClassName();
            $collName = $this->getCrossFKVarName($crossFK);
        }

        $script .= "
    /**
     * Sets a collection of $relatedObjectClassName objects related by a many-to-many relationship
     * to the current object by way of the $crossRefTableName cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param Collection \${$inputCollection} A Propel collection.
     * @param ConnectionInterface \$con Optional connection object
     * @return \$this The current object (for fluent API support)
     */
    public function set{$relatedNamePlural}(Collection \${$inputCollection}, ?ConnectionInterface \$con = null)
    {
        \$this->clear{$relatedNamePlural}();
        \$current{$relatedNamePlural} = \$this->get{$relatedNamePlural}();

        \${$scheduledForDeletionVarName} = \$current{$relatedNamePlural}->diff(\${$inputCollection});

        foreach (\${$scheduledForDeletionVarName} as \$toDelete) {";
        if ($multi) {
            $script .= "
            \$this->remove{$relatedName}(...\$toDelete);";
        } else {
            $script .= "
            \$this->remove{$relatedName}(\$toDelete);";
        }
        $script .= "
        }

        foreach (\${$inputCollection} as \${$foreachItem}) {";
        if ($multi) {
            $script .= "
            if (!\$current{$relatedNamePlural}->contains(...\${$foreachItem})) {
                \$this->doAdd{$relatedName}(...\${$foreachItem});
            }";
        } else {
            $script .= "
            if (!\$current{$relatedNamePlural}->contains(\${$foreachItem})) {
                \$this->doAdd{$relatedName}(\${$foreachItem});
            }";
        }
        $script .= "
        }

        \$this->{$collName}Partial = false;
        \$this->$collName = \${$inputCollection};

        return \$this;
    }
";
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    public function addCrossFKAttributes(string &$script, CrossForeignKeys $crossFKs): void
    {
        if ($crossFKs->hasCombinedKey()) {
            $localColumnName = $this->buildLocalColumnNameForCrossRef($crossFKs, true);
            [$names] = $this->getCrossFKInformation($crossFKs);
            $script .= "
    /**
     * @var ObjectCombinationCollection Cross CombinationCollection to store aggregation of $names combinations.
     */
    protected \$combination{$localColumnName};

    /**
     * @var bool
     */
    protected \$combination{$localColumnName}Partial;
";
        }

        foreach ($crossFKs->getCrossForeignKeys() as $fk) {
            $className = $this->resolveTargetTableClassName($fk);
            $localColumnName = '$' . $this->buildLocalColumnNameForCrossRef($crossFKs, false);

            $script .= "
    /**
     * @var        ObjectCollection|{$className}[] Cross Collection to store aggregation of $className objects.
     * @phpstan-var ObjectCollection&\Traversable<{$className}> Cross Collection to store aggregation of $className objects.
     */
    protected $localColumnName;

    /**
     * @var bool
     */
    protected {$localColumnName}Partial;
";
        }
    }

    /**
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return string
     */
    protected function getCrossScheduledForDeletionVarName(CrossForeignKeys $crossFKs): string
    {
        if ($crossFKs->hasCombinedKey()) {
            $relationName = $this->buildLocalColumnNameForCrossRef($crossFKs, true);

            return "combination{$relationName}ScheduledForDeletion";
        } else {
            $relationName = $this->resolveRelationForwardName($crossFKs, true, true);

            return "{$relationName}ScheduledForDeletion";
        }
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    public function addCrossFkScheduledForDeletion(string &$script, CrossForeignKeys $crossFKs): void
    {
        $multipleFks = $crossFKs->hasCombinedKey();
        $scheduledForDeletionVarName = $this->getCrossScheduledForDeletionVarName($crossFKs);
        $queryClassName = $this->getNewStubQueryBuilder($crossFKs->getMiddleTable())->getClassname();

        $crossPks = $crossFKs->getMiddleTable()->getPrimaryKey();

        $script .= "
            if (\$this->$scheduledForDeletionVarName !== null) {
                if (!\$this->{$scheduledForDeletionVarName}->isEmpty()) {
                    \$pks = [];";
        if ($multipleFks) {
            $script .= "
                    foreach (\$this->{$scheduledForDeletionVarName} as \$combination) {
                        \$entryPk = [];
";
            foreach ($crossFKs->getIncomingForeignKey()->getColumnObjectsMapping() as $reference) {
                $local = $reference['local'];
                $foreign = $reference['foreign'];

                $idx = array_search($local, $crossPks, true);
                $script .= "
                        \$entryPk[$idx] = \$this->get{$foreign->getPhpName()}();";
            }

            $combinationIdx = 0;
            foreach ($crossFKs->getCrossForeignKeys() as $crossFK) {
                foreach ($crossFK->getColumnObjectsMapping() as $reference) {
                    $local = $reference['local'];
                    $foreign = $reference['foreign'];

                    $idx = array_search($local, $crossPks, true);
                    $script .= "
                        \$entryPk[$idx] = \$combination[$combinationIdx]->get{$foreign->getPhpName()}();";
                }
                $combinationIdx++;
            }

            foreach ($crossFKs->getUnclassifiedPrimaryKeys() as $pk) {
                $idx = array_search($pk, $crossPks, true);
                $script .= "
                        //\$combination[$combinationIdx] = {$pk->getPhpName()};
                        \$entryPk[$idx] = \$combination[$combinationIdx];";
                $combinationIdx++;
            }

            $script .= "

                        \$pks[] = \$entryPk;
                    }
";

            $script .= "
                    $queryClassName::create()
                        ->filterByPrimaryKeys(\$pks)
                        ->delete(\$con);
";
        } else {
            $script .= "
                    foreach (\$this->{$scheduledForDeletionVarName} as \$entry) {
                        \$entryPk = [];
";

            foreach ($crossFKs->getIncomingForeignKey()->getColumnObjectsMapping() as $reference) {
                $local = $reference['local'];
                $foreign = $reference['foreign'];

                $idx = array_search($local, $crossPks, true);
                $script .= "
                        \$entryPk[$idx] = \$this->get{$foreign->getPhpName()}();";
            }

            $crossFK = $crossFKs->getCrossForeignKeys()[0];
            foreach ($crossFK->getColumnObjectsMapping() as $reference) {
                $local = $reference['local'];
                $foreign = $reference['foreign'];

                $idx = array_search($local, $crossPks, true);
                $script .= "
                        \$entryPk[$idx] = \$entry->get{$foreign->getPhpName()}();";
            }

            $script .= "
                        \$pks[] = \$entryPk;
                    }

                    {$queryClassName}::create()
                        ->filterByPrimaryKeys(\$pks)
                        ->delete(\$con);
";
        }

        $script .= "
                    \$this->$scheduledForDeletionVarName = null;
                }
";

        $script .= "
            }
";

        if ($multipleFks) {
            $combineVarName = 'combination' . $this->buildLocalColumnNameForCrossRef($crossFKs, true);
            $script .= "
            if (\$this->$combineVarName !== null) {
                foreach (\$this->$combineVarName as \$combination) {
";

            $combinationIdx = 0;
            foreach ($crossFKs->getCrossForeignKeys() as $crossFK) {
                $script .= "
                    //\$combination[$combinationIdx] = {$crossFK->getForeignTable()->getPhpName()} ({$crossFK->getName()})
                    if (!\$combination[$combinationIdx]->isDeleted() && (\$combination[$combinationIdx]->isNew() || \$combination[$combinationIdx]->isModified())) {
                        \$combination[$combinationIdx]->save(\$con);
                    }
                ";

                $combinationIdx++;
            }

            foreach ($crossFKs->getUnclassifiedPrimaryKeys() as $pk) {
                $script .= "
                    //\$combination[$combinationIdx] = {$pk->getPhpName()}; Nothing to save.";
                $combinationIdx++;
            }

            $script .= "
                }
            }
";
        } else {
            foreach ($crossFKs->getCrossForeignKeys() as $fk) {
                $relatedName = $this->nameProducer->resolveRelationForwardName($fk, true);
                $lowerSingleRelatedName = $this->nameProducer->resolveRelationForwardName($fk, false, true);

                $script .= "
            if (\$this->coll{$relatedName}) {
                foreach (\$this->coll{$relatedName} as \${$lowerSingleRelatedName}) {
                    if (!\${$lowerSingleRelatedName}->isDeleted() && (\${$lowerSingleRelatedName}->isNew() || \${$lowerSingleRelatedName}->isModified())) {
                        \${$lowerSingleRelatedName}->save(\$con);
                    }
                }
            }
";
            }
        }

        $script .= "
";
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    protected function addCrossFKCount(string &$script, CrossForeignKeys $crossFKs): void
    {
        $refFK = $crossFKs->getIncomingForeignKey();
        $selfRelationName = $this->nameProducer->resolveRelationForwardName($refFK, false);

        $multi = $crossFKs->hasCombinedKey();

        $relatedName = $this->resolveRelationForwardName($crossFKs, true);
        $crossRefTableName = $crossFKs->getMiddleTable()->getName();

        if ($multi) {
            [$relatedObjectClassName] = $this->getCrossFKInformation($crossFKs);
            $collName = 'combination' . $this->buildLocalColumnNameForCrossRef($crossFKs, true);
            $relatedQueryClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_QUERY_STUB, $crossFKs->getMiddleTable());
        } else {
            $crossFK = $crossFKs->getCrossForeignKeys()[0];
            $relatedObjectClassName = $this->getNewStubObjectBuilder($crossFK->getForeignTable())->getUnqualifiedClassName();
            $collName = $this->getCrossFKVarName($crossFK);
            $relatedQueryClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_QUERY_STUB, $crossFK->getForeignTable());
        }

        $script .= "
    /**
     * Gets the number of $relatedObjectClassName objects related by a many-to-many relationship
     * to the current object by way of the $crossRefTableName cross-reference table.
     *
     * @param Criteria \$criteria Optional query object to filter the query
     * @param bool \$distinct Set to true to force count distinct
     * @param ConnectionInterface \$con Optional connection object
     *
     * @return int The number of related $relatedObjectClassName objects
     */
    public function count{$relatedName}(?Criteria \$criteria = null, \$distinct = false, ?ConnectionInterface \$con = null): int
    {
        \$partial = \$this->{$collName}Partial && !\$this->isNew();
        if (\$this->$collName === null || \$criteria !== null || \$partial) {
            if (\$this->isNew() && \$this->$collName === null) {
                return 0;
            } else {

                if (\$partial && !\$criteria) {
                    return count(\$this->get$relatedName());
                }

                \$query = $relatedQueryClassName::create(null, \$criteria);
                if (\$distinct) {
                    \$query->distinct();
                }

                return \$query
                    ->filterBy{$selfRelationName}(\$this)
                    ->count(\$con);
            }
        } else {
            return count(\$this->$collName);
        }
    }
";

        if ($multi) {
            $relatedName = $this->resolveRelationForwardName($crossFKs, true);
            $firstFK = $crossFKs->getCrossForeignKeys()[0];
            $firstFkName = $this->nameProducer->resolveRelationForwardName($firstFK, true);

            $relatedObjectClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_OBJECT_STUB, $firstFK->getForeignTable());
            $signature = $shortSignature = $normalizedShortSignature = $phpDoc = [];
            $this->extractCrossInformation($crossFKs, [$firstFK], $signature, $shortSignature, $normalizedShortSignature, $phpDoc);

            $signature = array_map(function ($item) {
                return $item . ' = null';
            }, $signature);
            $signature = implode(', ', $signature);
            $phpDoc = implode(', ', $phpDoc);
            $shortSignature = implode(', ', $shortSignature);

            $script .= "
    
    /**
     * Returns the not cached count of $relatedObjectClassName objects. This will hit always the databases.
     * If you have attached new $relatedObjectClassName object to this object you need to call `save` first to get
     * the correct return value. Use get$relatedName() to get the current internal state.
     * $phpDoc
     * @param Criteria \$criteria
     * @param ConnectionInterface \$con
     *
     * @return int
     */
    public function count{$firstFkName}($signature, ?Criteria \$criteria = null, ?ConnectionInterface \$con = null): int
    {
        return \$this->create{$firstFkName}Query($shortSignature, \$criteria)->count(\$con);
    }
";
        }
    }

    /**
     * Adds the method that adds an object into the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    protected function addCrossFKAdd(string &$script, CrossForeignKeys $crossFKs): void
    {
        $refFK = $crossFKs->getIncomingForeignKey();

        foreach ($crossFKs->getCrossForeignKeys() as $crossFK) {
            if ($crossFKs->hasCombinedKey()) {
                $collName = 'combination' . $this->buildLocalColumnNameForCrossRef($crossFKs, true); // local column
                $relNamePlural = ucfirst($this->resolveRelationForwardName($crossFKs, true)); // relation combine name plural
                $relName = ucfirst($this->resolveRelationForwardName($crossFKs, false)); // relation combine name
            } else {
                $collName = $this->getCrossFKVarName($crossFK); // column single name i.e. 'collTeams'
                $relNamePlural = $this->nameProducer->resolveRelationForwardName($crossFK, true); // relation single name plural (?!?) i.e. 'Teams'
                $relName = $this->nameProducer->resolveRelationForwardName($crossFK, false); // relation single name i.e. 'Teams'
            }

            $tblFK = $refFK->getTable();
            $relatedObjectClassName = $this->resolveRelationForwardName($crossFKs, false);
            $crossObjectClassName = $this->resolveTargetTableClassName($crossFK);
            [$signature, $shortSignature, $normalizedShortSignature, $phpDoc] = $this->getCrossFKAddMethodInformation($crossFKs, $crossFK);

            $script .= "
    /**
     * Associate a $crossObjectClassName to this object
     * through the " . $tblFK->getName() . " cross reference table.
     *$phpDoc
     * @return " . $this->resolveInternalNameOfStubObject() . " The current object (for fluent API support)
     */
    public function add{$relatedObjectClassName}($signature)
    {
        if (\$this->" . $collName . " === null) {
            \$this->init" . $relNamePlural . "();
        }

        if (!\$this->get" . $relNamePlural . '()->contains(' . $normalizedShortSignature . ")) {
            // only add it if the **same** object is not already associated
            \$this->" . $collName . '->push(' . $normalizedShortSignature . ");
            \$this->doAdd{$relName}($normalizedShortSignature);
        }

        return \$this;
    }
";
        }
    }

    /**
     * Returns a function signature comma separated.
     *
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     * @param string $excludeSignatureItem Which variable to exclude.
     *
     * @return string
     */
    protected function getCrossFKGetterSignature(CrossForeignKeys $crossFKs, string $excludeSignatureItem): string
    {
        [, $getSignature] = $this->getCrossFKAddMethodInformation($crossFKs);
        $getSignature = explode(', ', $getSignature);

        $pos = array_search($excludeSignatureItem, $getSignature);
        if ($pos !== false) {
            unset($getSignature[$pos]);
        }

        return implode(', ', $getSignature);
    }

    /**
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    protected function buildDoAdd(string &$script, CrossForeignKeys $crossFKs): void
    {
        $script .= $crossFKs->hasCombinedKey() ? $this->buildDoAddWithMultiKey($crossFKs) : $this->buildDoAddWithSingleKey($crossFKs);
    }

    /**
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return string
     */
    protected function buildDoAddWithSingleKey(CrossForeignKeys $crossFKs): string
    {
        $relationKeys = $this->nameProducer->resolveRelationForwardName($crossFKs->getIncomingForeignKey(), true);
        $relatedObjectClassName = $this->resolveRelationForwardName($crossFKs, false);
        $targetClassName = $this->resolveSourceTableClassName($crossFKs->getIncomingForeignKey());

        $refKObjectClassName = $this->nameProducer->buildForeignKeyBackReferenceNameAffix($crossFKs->getIncomingForeignKey(), false);
        $tblFK = $crossFKs->getIncomingForeignKey()->getTable();
        $foreignObjectName = '$' . $tblFK->getCamelCaseName();

        [$signature, $shortSignature, $normalizedShortSignature, $phpDoc] = $this->getCrossFKAddMethodInformation($crossFKs);
        $fk = $crossFKs->getCrossForeignKeys()[0];
        $refFK = $crossFKs->getIncomingForeignKey();
        
        $relatedObject = '$' . $this->nameProducer->resolveRelationForwardName($fk, false, true);
        $getterArgs = $this->getCrossFKGetterSignature($crossFKs, $relatedObject);
        $relationNameOnOtherSide = $this->nameProducer->resolveRelationForwardName($refFK, false);

        $script = "
    /**
     *{$phpDoc}
     */
    protected function doAdd{$relatedObjectClassName}($signature)
    {
        {$foreignObjectName} = new {$targetClassName}();
        {$foreignObjectName}->set{$relatedObjectClassName}({$relatedObject});
        {$foreignObjectName}->set{$relationNameOnOtherSide}(\$this);

        \$this->add{$refKObjectClassName}({$foreignObjectName});

        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!{$relatedObject}->is{$relationKeys}Loaded()) {
            {$relatedObject}->init{$relationKeys}();
            {$relatedObject}->get{$relationKeys}($getterArgs)->push(\$this);
        } elseif (!{$relatedObject}->get{$relationKeys}($getterArgs)->contains(\$this)) {
            {$relatedObject}->get{$relationKeys}($getterArgs)->push(\$this);
        }
    }
";

        return $script;
    }

    /**
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return string
     */
    protected function buildDoAddWithMultiKey(CrossForeignKeys $crossFKs): string
    {
        $relatedObjectClassName = $this->resolveRelationForwardName($crossFKs, false);
        $className = $this->resolveSourceTableClassName($crossFKs->getIncomingForeignKey());

        $refKObjectClassName = $this->nameProducer->buildForeignKeyBackReferenceNameAffix($crossFKs->getIncomingForeignKey(), false);
        $tblFK = $crossFKs->getIncomingForeignKey()->getTable();
        $foreignObjectName = '$' . $tblFK->getCamelCaseName();

        [$signature, $shortSignature, $normalizedShortSignature, $phpDoc] = $this->getCrossFKAddMethodInformation($crossFKs);

        $script = "
    /**
     * {$phpDoc}
     */
    protected function doAdd{$relatedObjectClassName}($signature)
    {
        {$foreignObjectName} = new {$className}();";

            foreach ($crossFKs->getCrossForeignKeys() as $fK) {
                $targetKey = $this->nameProducer->resolveRelationForwardName($fK, false, true);
                $script .= "
        {$foreignObjectName}->set{$relatedObjectClassName}(\${$targetKey});";
            }

            foreach ($crossFKs->getUnclassifiedPrimaryKeys() as $primaryKey) {
                $paramName = lcfirst($primaryKey->getPhpName());
                $script .= "
        {$foreignObjectName}->set{$primaryKey->getPhpName()}(\$$paramName);\n";
            }

        $targetKeyOnOtherSide = $this->nameProducer->resolveRelationForwardName($crossFKs->getIncomingForeignKey(), false);

        $script .= "
        {$foreignObjectName}->set{$targetKeyOnOtherSide}(\$this);

        \$this->add{$refKObjectClassName}({$foreignObjectName});\n";

            foreach ($crossFKs->getCrossForeignKeys() as $crossFK) {
                $lowerRelatedObjectClassName = $this->nameProducer->resolveRelationForwardName($crossFK, false, true);

                $getterName = $this->getCrossRefFKGetterName($crossFKs, $crossFK);
                $getterRemoveObjectName = $this->getCrossRefFKRemoveObjectNames($crossFKs, $crossFK);

                $script .= "
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (\${$lowerRelatedObjectClassName}->is{$getterName}Loaded()) {
            \${$lowerRelatedObjectClassName}->init{$getterName}();
            \${$lowerRelatedObjectClassName}->get{$getterName}()->push($getterRemoveObjectName);
        } elseif (!\${$lowerRelatedObjectClassName}->get{$getterName}()->contains($getterRemoveObjectName)) {
            \${$lowerRelatedObjectClassName}->get{$getterName}()->push($getterRemoveObjectName);
        }
            ";
            }

        $script .= "
    }
";
        return $script;
    }

    /**
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     * @param \Propel\Generator\Model\ForeignKey $excludeFK
     *
     * @return string
     */
    protected function getCrossRefFKRemoveObjectNames(CrossForeignKeys $crossFKs, ForeignKey $excludeFK): string
    {
        $names = [];

        $fks = $crossFKs->getCrossForeignKeys();

        foreach ($crossFKs->getMiddleTable()->getForeignKeys() as $fk) {
            if ($fk !== $excludeFK && ($fk === $crossFKs->getIncomingForeignKey() || in_array($fk, $fks))) {
                if ($fk === $crossFKs->getIncomingForeignKey()) {
                    $names[] = '$this';
                } else {
                    $names[] = '$' . $this->nameProducer->resolveRelationForwardName($fk, false, true);
                }
            }
        }

        foreach ($crossFKs->getUnclassifiedPrimaryKeys() as $pk) {
            $names[] = '$' . lcfirst($pk->getPhpName());
        }

        return implode(', ', $names);
    }

    /**
     * Adds the method that remove an object from the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\CrossForeignKeys $crossFKs
     *
     * @return void
     */
    protected function addCrossFKRemove(string &$script, CrossForeignKeys $crossFKs): void
    {
        $relCol = $this->resolveRelationForwardName($crossFKs, true);
        $collName = $crossFKs->hasCombinedKey()
            ? 'combination' . $this->buildLocalColumnNameForCrossRef($crossFKs, true)
            : $this->buildLocalColumnNameForCrossRef($crossFKs, false);

        $tblFK = $crossFKs->getIncomingForeignKey()->getTable();

        $M2MScheduledForDeletion = $this->getCrossScheduledForDeletionVarName($crossFKs);
        $relatedObjectClassName = $this->resolveRelationForwardName($crossFKs, false);

        [$signature, $shortSignature, $normalizedShortSignature, $phpDoc] = $this->getCrossFKAddMethodInformation($crossFKs);
        $names = str_replace('$', '', $normalizedShortSignature);

        $className = $this->resolveSourceTableClassName($crossFKs->getIncomingForeignKey());
        $refKObjectClassName = $this->nameProducer->buildForeignKeyBackReferenceNameAffix($crossFKs->getIncomingForeignKey(), false);
        $foreignObjectName = '$' . $tblFK->getCamelCaseName();

        $script .= "
    /**
     * Remove $names of this object
     * through the {$tblFK->getName()} cross reference table.
     *$phpDoc
     * @return " . $this->resolveInternalNameOfStubObject() . " The current object (for fluent API support)
     */
    public function remove{$relatedObjectClassName}($signature)
    {
        if (\$this->get{$relCol}()->contains({$shortSignature})) {
            {$foreignObjectName} = new {$className}();";
        foreach ($crossFKs->getCrossForeignKeys() as $crossFK) {
            $relatedObjectClassName = $this->nameProducer->resolveRelationForwardName($crossFK, false);
            $lowerRelatedObjectClassName = lcfirst($relatedObjectClassName);

            $relatedObjectClassName = $this->nameProducer->resolveRelationForwardName($crossFK, false);
            $script .= "
            {$foreignObjectName}->set{$relatedObjectClassName}(\${$lowerRelatedObjectClassName});";

            $lowerRelatedObjectClassName = lcfirst($relatedObjectClassName);

            $getterName = $this->getCrossRefFKGetterName($crossFKs, $crossFK);
            $getterRemoveObjectName = $this->getCrossRefFKRemoveObjectNames($crossFKs, $crossFK);

            $script .= "
            if (\${$lowerRelatedObjectClassName}->is{$getterName}Loaded()) {
                //remove the back reference if available
                \${$lowerRelatedObjectClassName}->get$getterName()->removeObject($getterRemoveObjectName);
            }\n";
        }

        foreach ($crossFKs->getUnclassifiedPrimaryKeys() as $primaryKey) {
            $paramName = lcfirst($primaryKey->getPhpName());
            $script .= "
            {$foreignObjectName}->set{$primaryKey->getPhpName()}(\$$paramName);";
        }
        $script .= "
            {$foreignObjectName}->set{$this->nameProducer->resolveRelationForwardName($crossFKs->getIncomingForeignKey())}(\$this);";

        $script .= "
            \$this->remove{$refKObjectClassName}(clone {$foreignObjectName});
            {$foreignObjectName}->clear();

            \$this->{$collName}->remove(\$this->{$collName}->search({$shortSignature}));\n";

        $script .= "
            if (\$this->{$M2MScheduledForDeletion} === null) {
                \$this->{$M2MScheduledForDeletion} = clone \$this->{$collName};
                \$this->{$M2MScheduledForDeletion}->clear();
            }

            \$this->{$M2MScheduledForDeletion}->push({$shortSignature});
        }
";

        $script .= "

        return \$this;
    }
";
    }
}
