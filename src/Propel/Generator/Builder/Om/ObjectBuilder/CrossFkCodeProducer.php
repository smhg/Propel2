<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder;

use LogicException;
use Propel\Generator\Builder\DataModelBuilder;
use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Model\CrossForeignKeys;
use Propel\Generator\Model\ForeignKey;

/**
 * Generates a database loader file, which is used to register all table maps with the DatabaseMap.
 */
class CrossFkCodeProducer extends DataModelBuilder
{
    /**
     * @var CrossForeignKeys
     */
    protected $crossRelation;

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     */
    public function __construct(CrossForeignKeys $crossRelation, ObjectBuilder $builder)
    {
        parent::__construct($crossRelation->getTable(), $builder);
        $this->crossRelation = $crossRelation;
        if (!$builder->getGeneratorConfig()) {
            throw new LogicException('CrossFkCodeProducer should not be created before GeneratorConfig is available.');
        }
        $this->init($this->getTable(), $builder->getGeneratorConfig());
    }

    /**
     * @return CrossForeignKeys
     */
    public function getCrossRelation(): CrossForeignKeys
    {
        return $this->crossRelation;
    }

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
     * @param string $script
     *
     * @return void
     */
    public function addCrossFKMethods(string &$script): void
    {
        $this->registerTargetClasses();

            $this->addCrossFKClear($script);
            $this->addCrossFKInit($script);
            $this->addCrossFKisLoaded($script);
            $this->addCrossFKCreateQuery($script);
            $this->addCrossFKGet($script);
            $this->addCrossFKSet($script);
            $this->addCrossFKCount($script);
            $this->addCrossFKAdd($script);
            $this->buildDoAdd($script);
            $this->addCrossFKRemove($script);
            //$this->addCrossFKRemoves($script);
    }

    /**
     * @return void
     */
    public function registerTargetClasses(): void
    {
        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            $table = $fk->getForeignTable();
            $this->referencedClasses->registerBuilderResultClass($this->getNewStubObjectBuilder($table), 'Child');
            $this->referencedClasses->registerBuilderResultClass($this->getNewStubQueryBuilder($table));
        }
    }

    /**
     * Resolve name of cross relation from perspective of current table (in contrast to back-relation
     * from target table or regular fk-relation on middle table).
     * 
     * @param bool $plural
     * @param bool $lowercased
     *
     * @return string
     */
    protected function resolveRelationForwardName(bool $plural = true, bool $lowercased = false): string
    {
        $relationName = $this->buildCombineCrossFKsPhpNameAffix(false);

        $existingTable = $this->getDatabase()->getTableByPhpName($relationName);
        $isNameCollision = $existingTable && $this->getTable()->isConnectedWithTable($existingTable);
        if ($plural || $isNameCollision) {
            $relationName = $this->buildCombineCrossFKsPhpNameAffix($plural, $isNameCollision);
        }

        return $lowercased ? lcfirst($relationName) : $relationName;
    }

    /**
     * @return array<string>
     */
    protected function getCrossFKAddMethodInformation(?ForeignKey $k = null): array
    {
        $signature = $shortSignature = $normalizedShortSignature = $phpDoc = [];
        if ($k instanceof ForeignKey) {
            $crossObjectName = '$' . $this->nameProducer->resolveRelationForwardName($k, false, true);
            $crossObjectClassName = $this->resolveTargetTableClassName($k);
            $signature[] = "$crossObjectClassName $crossObjectName" . ($k->isAtLeastOneLocalColumnRequired() ? '' : ' = null');
            $shortSignature[] = $crossObjectName;
            $normalizedShortSignature[] = $crossObjectName;
            $phpDoc[] = "
     * @param $crossObjectClassName $crossObjectName";
        } elseif ($k == null) {
            $k = [];
        }

        $this->extractCrossInformation($k, $signature, $shortSignature, $normalizedShortSignature, $phpDoc);

        $signature = implode(', ', $signature);
        $shortSignature = implode(', ', $shortSignature);
        $normalizedShortSignature = implode(', ', $normalizedShortSignature);
        $phpDoc = implode(', ', $phpDoc);

        return [$signature, $shortSignature, $normalizedShortSignature, $phpDoc];
    }

    /**
     * Extracts some useful information from a CrossForeignKeys object.
     *
     * @param \Propel\Generator\Model\ForeignKey|array $crossFKToIgnore
     * @param array $signature
     * @param array $shortSignature
     * @param array $normalizedShortSignature
     * @param array $phpDoc
     *
     * @return void
     */
    protected function extractCrossInformation(
        $crossFKToIgnore,
        array &$signature,
        array &$shortSignature,
        array &$normalizedShortSignature,
        array &$phpDoc
    ): void {
        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
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

        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $primaryKey) {
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
     * @param bool $plural
     * @param bool $withPrefix
     *
     * @return string
     */
    protected function buildCombineCrossFKsPhpNameAffix(bool $plural = true, bool $withPrefix = false): string
    {
        $names = [];
        if ($withPrefix) {
            $names[] = 'Cross';
        }
        $fks = $this->crossRelation->getCrossForeignKeys();
        $lastCrossFk = array_pop($fks);
        $unclassifiedPrimaryKeys = $this->crossRelation->getUnclassifiedPrimaryKeys();
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
     * @param \Propel\Generator\Model\ForeignKey $excludeFK
     *
     * @return string
     */
    protected function getCrossRefFKGetterName(ForeignKey $excludeFK): string
    {
        $names = [];

        $fks = $this->crossRelation->getCrossForeignKeys();

        foreach ($this->crossRelation->getMiddleTable()->getForeignKeys() as $fk) {
            if ($fk !== $excludeFK && ($fk === $this->crossRelation->getIncomingForeignKey() || in_array($fk, $fks))) {
                $names[] = $this->nameProducer->resolveRelationForwardName($fk, false);
            }
        }

        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $pk) {
            $names[] = $pk->getPhpName();
        }

        $name = implode('', $names);

        return $this->getPluralizer()->getPluralForm($name);
    }

    /**
     * @return array
     */
    protected function getCrossFKInformation(): array
    {
        $names = [];
        $signatures = [];
        $shortSignature = [];
        $phpDoc = [];

        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
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
     *
     * @return void
     */
    protected function addCrossFKClear(string &$script): void
    {
        $relCol = $this->resolveRelationForwardName();
        $collName = $this->buildLocalColumnNameForCrossRef(false);

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
     *
     * @return void
     */
    public function addCrossScheduledForDeletionAttribute(string &$script): void
    {
        $script .= $this->crossRelation->hasCombinedKey() 
            ? $this->buildScheduledForDeletionAttributeWithCombinedKey()
            : $this->buildScheduledForDeletionAttributeWithSimpleKey();
    }

    /**
     * @return string
     */
    protected function buildScheduledForDeletionAttributeWithSimpleKey(): string
    {
        $refFK = $this->crossRelation->getIncomingForeignKey();
        if ($refFK->isLocalPrimaryKey()) {
            return '';
        }
        $name = $this->getCrossScheduledForDeletionVarName();
        $className = $this->resolveTargetTableClassName($this->crossRelation->getCrossForeignKeys()[0]);

        return "
    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection|{$className}[]
     * @phpstan-var ObjectCollection&\Traversable<{$className}>
     */
    protected \$$name = null;\n";
    }

    /**
     * @return string
     */
    protected function buildScheduledForDeletionAttributeWithCombinedKey(): string
    {
        $name = $this->getCrossScheduledForDeletionVarName();
        [$names] = $this->getCrossFKInformation();

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
     * @param bool $uppercaseFirstChar
     *
     * @return string
     */
    public function buildLocalColumnNameForCrossRef(bool $uppercaseFirstChar): string
    {
        $columnName = 'coll' . $this->resolveRelationForwardName();

        return $uppercaseFirstChar ? ucfirst($columnName) : $columnName;
    }

    /**
     * Adds the method that initializes the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addCrossFKInit(string &$script): void
    {
        if ($this->crossRelation->hasCombinedKey()) {

            $columnName = 'combination' . $this->buildLocalColumnNameForCrossRef( true);
            $relationName = $this->resolveRelationForwardName(true);
            $collectionClassName = 'ObjectCombinationCollection';

            $this->buildInitCode($script, $columnName, $relationName, $collectionClassName, null, null);
        } else {
            foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
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
     *
     * @return void
     */
    protected function addCrossFKIsLoaded(string &$script): void
    {
        $inits = [];

        if ($this->crossRelation->hasCombinedKey()) {
            $inits[] = [
                'relCol' => $this->resolveRelationForwardName( true),
                'collName' => 'combination' . $this->buildLocalColumnNameForCrossRef(true),
            ];
        } else {
            foreach ($this->crossRelation->getCrossForeignKeys() as $crossFK) {
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
     *
     * @return void
     */
    protected function addCrossFKCreateQuery(string &$script): void
    {
        if (!$this->crossRelation->hasCombinedKey()) {
            return;
        }

        $refFK = $this->crossRelation->getIncomingForeignKey();
        $selfRelationName = $this->nameProducer->resolveRelationForwardName($refFK, false);
        $firstFK = $this->crossRelation->getCrossForeignKeys()[0];
        $firstFkName = $this->nameProducer->resolveRelationForwardName($firstFK, true);

        $relatedQueryClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_QUERY_STUB, $firstFK->getForeignTable());
        $signature = $shortSignature = $normalizedShortSignature = $phpDoc = [];
        $this->extractCrossInformation([$firstFK], $signature, $shortSignature, $normalizedShortSignature, $phpDoc);

        $signature = array_map(function ($item) {
            return $item . ' = null';
        }, $signature);
        $signature = implode(', ', $signature);
        $phpDoc = implode(', ', $phpDoc);

        $relatedUseQueryClassName = $this->getNewStubQueryBuilder($this->crossRelation->getMiddleTable())->getUnqualifiedClassName();
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

        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            if ($this->crossRelation->getIncomingForeignKey() === $fk || $firstFK === $fk) {
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
        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $pk) {
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
     *
     * @return void
     */
    protected function addCrossFKGet(string &$script): void
    {
        $refFK = $this->crossRelation->getIncomingForeignKey();
        $selfRelationName = $this->nameProducer->resolveRelationForwardName($refFK, false);
        $crossRefTableName = $this->crossRelation->getMiddleTable()->getName();

        if ($this->crossRelation->hasCombinedKey()) {
            $relatedName = $this->resolveRelationForwardName(true);
            $collVarName = 'combination' . $this->buildLocalColumnNameForCrossRef(true);

            $classNames = [];
            foreach ($this->crossRelation->getCrossForeignKeys() as $crossFK) {
                $classNames[] = $this->resolveClassNameForTable(GeneratorConfig::KEY_OBJECT_STUB, $crossFK->getForeignTable());
            }
            $classNames = implode(', ', $classNames);
            $relatedQueryClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_QUERY_STUB, $this->crossRelation->getMiddleTable());

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
            foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
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

            foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
                $varName = $this->nameProducer->resolveRelationForwardName($fk, false);
                $script .= "
                    \$combination[] = \$item->get{$varName}();";
            }

            foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $pk) {
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

            $relatedName = $this->resolveRelationForwardName(true);
            $firstFK = $this->crossRelation->getCrossForeignKeys()[0];
            $firstFkName = $this->nameProducer->resolveRelationForwardName($firstFK, true);

            $relatedObjectClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_OBJECT_STUB, $firstFK->getForeignTable());
            $signature = $shortSignature = $normalizedShortSignature = $phpDoc = [];
            $this->extractCrossInformation([$firstFK], $signature, $shortSignature, $normalizedShortSignature, $phpDoc);

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

        foreach ($this->crossRelation->getCrossForeignKeys() as $crossFK) {
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
     *
     * @return void
     */
    protected function addCrossFKSet(string &$script): void
    {
        $scheduledForDeletionVarName = $this->getCrossScheduledForDeletionVarName();

        $multi = $this->crossRelation->hasCombinedKey();

        $relatedNamePlural = $this->resolveRelationForwardName(true);
        $relatedName = $this->resolveRelationForwardName(false);
        $inputCollection = lcfirst($relatedNamePlural);
        $foreachItem = lcfirst($relatedName);
        $crossRefTableName = $this->crossRelation->getMiddleTable()->getName();

        if ($multi) {
            [$relatedObjectClassName] = $this->getCrossFKInformation();
            $collName = 'combination' . $this->buildLocalColumnNameForCrossRef(true);
        } else {
            $crossFK = $this->crossRelation->getCrossForeignKeys()[0];
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
     *
     * @return void
     */
    public function addAttributes(string &$script): void
    {
        if ($this->crossRelation->hasCombinedKey()) {
            $localColumnName = $this->buildLocalColumnNameForCrossRef(true);
            [$names] = $this->getCrossFKInformation();
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

        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            $className = $this->resolveTargetTableClassName($fk);
            $localColumnName = '$' . $this->buildLocalColumnNameForCrossRef(false);

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
     * @return string
     */
    protected function getCrossScheduledForDeletionVarName(): string
    {
        if ($this->crossRelation->hasCombinedKey()) {
            $relationName = $this->buildLocalColumnNameForCrossRef(true);

            return "combination{$relationName}ScheduledForDeletion";
        } else {
            $relationName = $this->resolveRelationForwardName(true, true);

            return "{$relationName}ScheduledForDeletion";
        }
    }

    /**
     * @param string $script
     *
     * @return void
     */
    public function addCrossFkScheduledForDeletion(string &$script): void
    {
        $multipleFks = $this->crossRelation->hasCombinedKey();
        $scheduledForDeletionVarName = $this->getCrossScheduledForDeletionVarName();
        $queryClassName = $this->getNewStubQueryBuilder($this->crossRelation->getMiddleTable())->getClassname();

        $crossPks = $this->crossRelation->getMiddleTable()->getPrimaryKey();

        $script .= "
            if (\$this->$scheduledForDeletionVarName !== null) {
                if (!\$this->{$scheduledForDeletionVarName}->isEmpty()) {
                    \$pks = [];";
        if ($multipleFks) {
            $script .= "
                    foreach (\$this->{$scheduledForDeletionVarName} as \$combination) {
                        \$entryPk = [];
";
            foreach ($this->crossRelation->getIncomingForeignKey()->getColumnObjectsMapping() as $reference) {
                $local = $reference['local'];
                $foreign = $reference['foreign'];

                $idx = array_search($local, $crossPks, true);
                $script .= "
                        \$entryPk[$idx] = \$this->get{$foreign->getPhpName()}();";
            }

            $combinationIdx = 0;
            foreach ($this->crossRelation->getCrossForeignKeys() as $crossFK) {
                foreach ($crossFK->getColumnObjectsMapping() as $reference) {
                    $local = $reference['local'];
                    $foreign = $reference['foreign'];

                    $idx = array_search($local, $crossPks, true);
                    $script .= "
                        \$entryPk[$idx] = \$combination[$combinationIdx]->get{$foreign->getPhpName()}();";
                }
                $combinationIdx++;
            }

            foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $pk) {
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

            foreach ($this->crossRelation->getIncomingForeignKey()->getColumnObjectsMapping() as $reference) {
                $local = $reference['local'];
                $foreign = $reference['foreign'];

                $idx = array_search($local, $crossPks, true);
                $script .= "
                        \$entryPk[$idx] = \$this->get{$foreign->getPhpName()}();";
            }

            $crossFK = $this->crossRelation->getCrossForeignKeys()[0];
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
            $combineVarName = 'combination' . $this->buildLocalColumnNameForCrossRef(true);
            $script .= "
            if (\$this->$combineVarName !== null) {
                foreach (\$this->$combineVarName as \$combination) {
";

            $combinationIdx = 0;
            foreach ($this->crossRelation->getCrossForeignKeys() as $crossFK) {
                $script .= "
                    //\$combination[$combinationIdx] = {$crossFK->getForeignTable()->getPhpName()} ({$crossFK->getName()})
                    if (!\$combination[$combinationIdx]->isDeleted() && (\$combination[$combinationIdx]->isNew() || \$combination[$combinationIdx]->isModified())) {
                        \$combination[$combinationIdx]->save(\$con);
                    }
                ";

                $combinationIdx++;
            }

            foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $pk) {
                $script .= "
                    //\$combination[$combinationIdx] = {$pk->getPhpName()}; Nothing to save.";
                $combinationIdx++;
            }

            $script .= "
                }
            }
";
        } else {
            foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
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
     *
     * @return void
     */
    protected function addCrossFKCount(string &$script): void
    {
        $refFK = $this->crossRelation->getIncomingForeignKey();
        $selfRelationName = $this->nameProducer->resolveRelationForwardName($refFK, false);

        $multi = $this->crossRelation->hasCombinedKey();

        $relatedName = $this->resolveRelationForwardName(true);
        $crossRefTableName = $this->crossRelation->getMiddleTable()->getName();

        if ($multi) {
            [$relatedObjectClassName] = $this->getCrossFKInformation();
            $collName = 'combination' . $this->buildLocalColumnNameForCrossRef(true);
            $relatedQueryClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_QUERY_STUB, $this->crossRelation->getMiddleTable());
        } else {
            $crossFK = $this->crossRelation->getCrossForeignKeys()[0];
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
            $relatedName = $this->resolveRelationForwardName(true);
            $firstFK = $this->crossRelation->getCrossForeignKeys()[0];
            $firstFkName = $this->nameProducer->resolveRelationForwardName($firstFK, true);

            $relatedObjectClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_OBJECT_STUB, $firstFK->getForeignTable());
            $signature = $shortSignature = $normalizedShortSignature = $phpDoc = [];
            $this->extractCrossInformation([$firstFK], $signature, $shortSignature, $normalizedShortSignature, $phpDoc);

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
     *
     * @return void
     */
    protected function addCrossFKAdd(string &$script): void
    {
        $refFK = $this->crossRelation->getIncomingForeignKey();

        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            if ($this->crossRelation->hasCombinedKey()) {
                $collName = 'combination' . $this->buildLocalColumnNameForCrossRef(true); // local column
                $relNamePlural = ucfirst($this->resolveRelationForwardName(true)); // relation combine name plural
                $relName = ucfirst($this->resolveRelationForwardName(false)); // relation combine name
            } else {
                $collName = $this->getCrossFKVarName($fk); // column single name i.e. 'collTeams'
                $relNamePlural = $this->nameProducer->resolveRelationForwardName($fk, true); // relation single name plural (?!?) i.e. 'Teams'
                $relName = $this->nameProducer->resolveRelationForwardName($fk, false); // relation single name i.e. 'Teams'
            }

            $tblFK = $refFK->getTable();
            $relatedObjectClassName = $this->resolveRelationForwardName(false);
            $crossObjectClassName = $this->resolveTargetTableClassName($fk);
            [$signature, $shortSignature, $normalizedShortSignature, $phpDoc] = $this->getCrossFKAddMethodInformation($fk);

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
     * @param string $excludeSignatureItem Which variable to exclude.
     *
     * @return string
     */
    protected function getCrossFKGetterSignature(string $excludeSignatureItem): string
    {
        [, $getSignature] = $this->getCrossFKAddMethodInformation();
        $getSignature = explode(', ', $getSignature);

        $pos = array_search($excludeSignatureItem, $getSignature);
        if ($pos !== false) {
            unset($getSignature[$pos]);
        }

        return implode(', ', $getSignature);
    }

    /**
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function buildDoAdd(string &$script): void
    {
        $script .= $this->crossRelation->hasCombinedKey() ? $this->buildDoAddWithMultiKey() : $this->buildDoAddWithSingleKey();
    }

    /**
     * @return string
     */
    protected function buildDoAddWithSingleKey(): string
    {
        $relationKeys = $this->nameProducer->resolveRelationForwardName($this->crossRelation->getIncomingForeignKey(), true);
        $relatedObjectClassName = $this->resolveRelationForwardName(false);
        $targetClassName = $this->resolveSourceTableClassName($this->crossRelation->getIncomingForeignKey());

        $refKObjectClassName = $this->nameProducer->buildForeignKeyBackReferenceNameAffix($this->crossRelation->getIncomingForeignKey(), false);
        $tblFK = $this->crossRelation->getIncomingForeignKey()->getTable();
        $foreignObjectName = '$' . $tblFK->getCamelCaseName();

        [$signature, $shortSignature, $normalizedShortSignature, $phpDoc] = $this->getCrossFKAddMethodInformation();
        $fk = $this->crossRelation->getCrossForeignKeys()[0];
        $refFK = $this->crossRelation->getIncomingForeignKey();
        
        $relatedObject = '$' . $this->nameProducer->resolveRelationForwardName($fk, false, true);
        $getterArgs = $this->getCrossFKGetterSignature($relatedObject);
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
     * @return string
     */
    protected function buildDoAddWithMultiKey(): string
    {
        $relatedObjectClassName = $this->resolveRelationForwardName(false);
        $className = $this->resolveSourceTableClassName($this->crossRelation->getIncomingForeignKey());

        $refKObjectClassName = $this->nameProducer->buildForeignKeyBackReferenceNameAffix($this->crossRelation->getIncomingForeignKey(), false);
        $tblFK = $this->crossRelation->getIncomingForeignKey()->getTable();
        $foreignObjectName = '$' . $tblFK->getCamelCaseName();

        [$signature, $shortSignature, $normalizedShortSignature, $phpDoc] = $this->getCrossFKAddMethodInformation();

        $script = "
    /**
     * {$phpDoc}
     */
    protected function doAdd{$relatedObjectClassName}($signature)
    {
        {$foreignObjectName} = new {$className}();";

            foreach ($this->crossRelation->getCrossForeignKeys() as $fK) {
                $targetKey = $this->nameProducer->resolveRelationForwardName($fK, false, true);
                $script .= "
        {$foreignObjectName}->set{$relatedObjectClassName}(\${$targetKey});";
            }

            foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $primaryKey) {
                $paramName = lcfirst($primaryKey->getPhpName());
                $script .= "
        {$foreignObjectName}->set{$primaryKey->getPhpName()}(\$$paramName);\n";
            }

        $targetKeyOnOtherSide = $this->nameProducer->resolveRelationForwardName($this->crossRelation->getIncomingForeignKey(), false);

        $script .= "
        {$foreignObjectName}->set{$targetKeyOnOtherSide}(\$this);

        \$this->add{$refKObjectClassName}({$foreignObjectName});\n";

            foreach ($this->crossRelation->getCrossForeignKeys() as $crossFK) {
                $lowerRelatedObjectClassName = $this->nameProducer->resolveRelationForwardName($crossFK, false, true);

                $getterName = $this->getCrossRefFKGetterName($crossFK);
                $getterRemoveObjectName = $this->getCrossRefFKRemoveObjectNames($crossFK);

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
     * @param \Propel\Generator\Model\ForeignKey $excludeFK
     *
     * @return string
     */
    protected function getCrossRefFKRemoveObjectNames(ForeignKey $excludeFK): string
    {
        $names = [];

        $fks = $this->crossRelation->getCrossForeignKeys();

        foreach ($this->crossRelation->getMiddleTable()->getForeignKeys() as $fk) {
            if ($fk !== $excludeFK && ($fk === $this->crossRelation->getIncomingForeignKey() || in_array($fk, $fks))) {
                if ($fk === $this->crossRelation->getIncomingForeignKey()) {
                    $names[] = '$this';
                } else {
                    $names[] = '$' . $this->nameProducer->resolveRelationForwardName($fk, false, true);
                }
            }
        }

        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $pk) {
            $names[] = '$' . lcfirst($pk->getPhpName());
        }

        return implode(', ', $names);
    }

    /**
     * Adds the method that remove an object from the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addCrossFKRemove(string &$script): void
    {
        $relCol = $this->resolveRelationForwardName(true);
        $collName = $this->crossRelation->hasCombinedKey()
            ? 'combination' . $this->buildLocalColumnNameForCrossRef(true)
            : $this->buildLocalColumnNameForCrossRef(false);

        $tblFK = $this->crossRelation->getIncomingForeignKey()->getTable();

        $M2MScheduledForDeletion = $this->getCrossScheduledForDeletionVarName();
        $relatedObjectClassName = $this->resolveRelationForwardName(false);

        [$signature, $shortSignature, $normalizedShortSignature, $phpDoc] = $this->getCrossFKAddMethodInformation();
        $names = str_replace('$', '', $normalizedShortSignature);

        $className = $this->resolveSourceTableClassName($this->crossRelation->getIncomingForeignKey());
        $refKObjectClassName = $this->nameProducer->buildForeignKeyBackReferenceNameAffix($this->crossRelation->getIncomingForeignKey(), false);
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
        foreach ($this->crossRelation->getCrossForeignKeys() as $crossFK) {
            $relatedObjectClassName = $this->nameProducer->resolveRelationForwardName($crossFK, false);
            $lowerRelatedObjectClassName = lcfirst($relatedObjectClassName);

            $relatedObjectClassName = $this->nameProducer->resolveRelationForwardName($crossFK, false);
            $script .= "
            {$foreignObjectName}->set{$relatedObjectClassName}(\${$lowerRelatedObjectClassName});";

            $lowerRelatedObjectClassName = lcfirst($relatedObjectClassName);

            $getterName = $this->getCrossRefFKGetterName($crossFK);
            $getterRemoveObjectName = $this->getCrossRefFKRemoveObjectNames($crossFK);

            $script .= "
            if (\${$lowerRelatedObjectClassName}->is{$getterName}Loaded()) {
                //remove the back reference if available
                \${$lowerRelatedObjectClassName}->get$getterName()->removeObject($getterRemoveObjectName);
            }\n";
        }

        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $primaryKey) {
            $paramName = lcfirst($primaryKey->getPhpName());
            $script .= "
            {$foreignObjectName}->set{$primaryKey->getPhpName()}(\$$paramName);";
        }
        $script .= "
            {$foreignObjectName}->set{$this->nameProducer->resolveRelationForwardName($this->crossRelation->getIncomingForeignKey())}(\$this);";

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
