<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Config\GeneratorConfigInterface;
use Propel\Generator\Model\CrossRelation;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\Table;

/**
 * Generates a database loader file, which is used to register all table maps with the DatabaseMap.
 */
abstract class AbstractManyToManyCodeProducer extends AbstractRelationCodeProducer
{
    /**
     * @var string
     */
    protected const ATTRIBUTE_PREFIX = 'coll'; // abbrev for 'collection' TODO: fix

    /**
     * @var \Propel\Generator\Model\CrossRelation
     */
    protected $crossRelation;

    /**
     * @var \Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer\CrossRelationNames
     */
    protected $names;

    /**
     * @param \Propel\Generator\Model\CrossRelation $crossRelation
     * @param \Propel\Generator\Builder\Om\ObjectBuilder $parentBuilder
     */
    protected function __construct(CrossRelation $crossRelation, ObjectBuilder $parentBuilder)
    {
        $this->crossRelation = $crossRelation;
        parent::__construct($crossRelation->getTable(), $parentBuilder);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Config\GeneratorConfigInterface|null $generatorConfig
     *
     * @return void
     */
    #[\Override]
    protected function init(Table $table, ?GeneratorConfigInterface $generatorConfig): void
    {
        parent::init($table, $generatorConfig);
        if (!$generatorConfig) {
            return;
        }
        $this->names = new CrossRelationNames(
            $this->crossRelation,
            static::ATTRIBUTE_PREFIX,
            $this->nameProducer,
            $this->referencedClasses,
        );
    }

    /**
     * @param \Propel\Generator\Model\CrossRelation $crossRelation
     * @param \Propel\Generator\Builder\Om\ObjectBuilder $builder
     *
     * @return \Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer\ManyToManyRelationCodeProducer|\Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer\TernaryRelationCodeProducer
     */
    public static function create(CrossRelation $crossRelation, ObjectBuilder $builder): self
    {
        return $crossRelation->isMultiModel()
            ? new TernaryRelationCodeProducer($crossRelation, $builder)
            : new ManyToManyRelationCodeProducer($crossRelation, $builder);
    }

    /**
     * @return \Propel\Generator\Model\CrossRelation
     */
    public function getCrossRelation(): CrossRelation
    {
        return $this->crossRelation;
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

        $this->addClear($script);
        $this->addInit($script);
        $this->addIsLoaded($script);
        $this->addCreateQuery($script);
        $this->addGetters($script);
        $this->addSetters($script);
        $this->addCount($script);
        $this->addAdders($script);
        $this->buildDoAdd($script);
        $this->addRemove($script);
    }

    /**
     * Adds the method that initializes the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    abstract protected function addInit(string &$script): void;

    /**
     * @param string $script
     *
     * @return void
     */
    abstract protected function addCreateQuery(string &$script): void;

    /**
     * @param string $script
     *
     * @return void
     */
    abstract protected function addGetters(string &$script): void;

    /**
     * @return bool
     */
    abstract protected function setterItemIsArray(): bool;

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    abstract public function addDeleteScheduledItemsCode(string &$script): void;

    /**
     * @return string
     */
    abstract protected function buildAdditionalCountMethods(): string;

    /**
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    abstract protected function buildDoAdd(string &$script): void;

    /**
     * Reports the names used in getters/setters created for this cross relation.
     *
     * Names should be in singular form. Used for schema validation.
     *
     * @return array<string>
     */
    abstract public function reserveNamesForGetters(): array;

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
     * Collect signature from keys, but the supplied Fk first.
     *
     * @param \Propel\Generator\Model\ForeignKey $firstFk
     *
     * @return \Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer\FunctionArgumentSignatureCollector
     */
    protected function collectSignatureWithFirstArgument(ForeignKey $firstFk): FunctionArgumentSignatureCollector
    {
        $collector = new FunctionArgumentSignatureCollector();
            $crossObjectName = '$' . $this->nameProducer->resolveRelationIdentifier($firstFk, false, true);
            $crossObjectClassName = $this->referencedClasses->resolveForeignKeyTargetModelClassName($firstFk);
            $typeHint = $firstFk->usesNotNullSourceColumn() ? null : 'null';

            $collector->addEntry($crossObjectName, $crossObjectClassName, $crossObjectClassName, $typeHint);

        return $this->collectSignature($firstFk, $collector);
    }

    /**
     * Collect signature from keys.
     *
     * @param \Propel\Generator\Model\ForeignKey|null $fkToIgnore
     * @param \Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer\FunctionArgumentSignatureCollector|null $collector
     * @param string|null $withDefaultValue Set to {@see FunctionArgumentSignatureCollector::USE_COLUMN_DEFAULT} or {@see FunctionArgumentSignatureCollector::USE_DEFAULT_NULL} to add default values to argument declarations.
     *
     * @return \Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer\FunctionArgumentSignatureCollector
     */
    protected function collectSignature(
        ?ForeignKey $fkToIgnore = null,
        ?FunctionArgumentSignatureCollector $collector = null,
        ?string $withDefaultValue = null
    ): FunctionArgumentSignatureCollector {
        $collector = $collector ?: new FunctionArgumentSignatureCollector();

        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            if ($fk === $fkToIgnore) {
                continue;
            }

            $phpType = $typeHint = $this->referencedClasses->resolveForeignKeyTargetModelClassName($fk);
            $name = '$' . $this->nameProducer->resolveRelationIdentifier($fk, false, true);

            $collector->addEntry($name, $phpType, $typeHint);
        }

        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $column) {
            //we need to add all those $primaryKey s as additional parameter as they are needed
            //to create the entry in the middle-table.
            $collector->addColumn($column, $withDefaultValue);
        }

        return $collector;
    }

    /**
     * Concat relation Fk names and unclassified primary keys into a single string.
     *
     * @param \Propel\Generator\Model\ForeignKey $excludeFK
     *
     * @return string
     */
    protected function concatRelationTargetNames(ForeignKey $excludeFK): string
    {
        $keys = $this->crossRelation->getKeysInOrder($excludeFK);
        $name = '';
        foreach ($keys as $fk) {
            $name .= $this->nameProducer->resolveRelationIdentifier($fk, false);
        }

        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $pk) {
            $name .= $pk->getPhpName();
        }

        return $this->getPluralizer()->getPluralForm($name);
    }

    /**
     * Builds function argument string, where args are cross relation fk targets
     * in fk declaration order on middle table and unclassified PKs of middle table.
     *
     * @param \Propel\Generator\Model\ForeignKey $excludeFK
     *
     * @return string
     */
    protected function buildMiddleTableArgumentCsv(ForeignKey $excludeFK): string
    {
        $orderedRelationFks = $this->crossRelation->getKeysInOrder($excludeFK);
        $keyMiddleToSource = $this->crossRelation->getIncomingForeignKey();

        $names = [];
        foreach ($orderedRelationFks as $keyMiddle) {
            $names[] = $keyMiddle === $keyMiddleToSource
                ? '$this'
                : '$' . $this->nameProducer->resolveRelationIdentifier($keyMiddle, false, true);
        }

        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $pk) {
            $names[] = '$' . lcfirst($pk->getPhpName());
        }

        return implode(', ', $names);
    }

    /**
     * Adds attribute declaration for every relation to target and a combined collection if
     * there is more than one relation.
     *
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addAttributes(string &$script): void
    {
        $attributeName = '$' . $this->names->getAttributeWithCollectionName();
        $attributePartialName = '$' . $this->names->getAttributeIsPartialName();
        $relationIdentifier = $this->names->getTargetIdentifier(false);
        [$_, $objectCollectionType] = $this->resolveObjectCollectionClassNameAndType();

        $script .= "
    /**
     * @var $objectCollectionType Objects in $relationIdentifier relation.
     */
    protected $attributeName;

    /**
     * @var bool
     */
    protected $attributePartialName;\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    public function addScheduledForDeletionAttribute(string &$script): void
    {
        $attributeName = $this->names->getAttributeScheduledForDeletionName();
        $targetIdentifierSingular = $this->names->getTargetIdentifier(false);
        [$_, $objectCollectionType] = $this->resolveObjectCollectionClassNameAndType();

        $script .= "
    /**
     * Items of $targetIdentifierSingular relation marked for deletion.
     *
     * @var $objectCollectionType
     */
    protected \$$attributeName = null;\n";
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
        $targetIdentifierPlural = $this->names->getTargetIdentifier(true);
        $attributeName = $this->names->getAttributeWithCollectionName();

        $script .= "
    /**
     * Clears out the {$attributeName} collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     */
    public function clear{$targetIdentifierPlural}(): void
    {
        \$this->$attributeName = null; // important to set this to NULL since that means it is uninitialized
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
        $varName = $this->names->getAttributeWithCollectionName();

        $script .= "
        if (\$this->$varName) {
            foreach (\$this->$varName as \$o) {
                \$o->clearAllReferences(\$deep);
            }
        }";

        return $varName;
    }

    /**
     * @param string|null $collectionClass
     * @param string|null $foreignTableMapName
     * @param string|null $relatedObjectClassName
     *
     * @return string
     */
    protected function buildInitCode(
        ?string $collectionClass,
        ?string $foreignTableMapName,
        ?string $relatedObjectClassName
    ): string {
        $targetIdentifierPlural = $this->names->getTargetIdentifier(true);
        $attributeName = $this->names->getAttributeWithCollectionName();
        $attributePartialName = $this->names->getAttributeIsPartialName();

        $script = "
    /**
     * Initializes the $attributeName crossRef collection.
     *
     * By default this just sets the $attributeName collection to an empty collection (like clear$targetIdentifierPlural());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function init$targetIdentifierPlural(): void
    {";

        if ($collectionClass) {
                $script .= "
        \$this->$attributeName = new $collectionClass();";
        } else {
            $script .= "
        \$collectionClassName = $foreignTableMapName::getTableMap()->getCollectionClassName();
        \$this->$attributeName = new \$collectionClassName;";
        }

            $script .= "
        \$this->{$attributePartialName} = true;";

        if ($relatedObjectClassName) {
            $script .= "
        \$this->{$attributeName}->setModel('$relatedObjectClassName');";
        }

            $script .= "
    }
";

        return $script;
    }

    /**
     * Adds the method that check if the referrer fkey collection is initialized.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addIsLoaded(string &$script): void
    {
        $targetIdentifierPlural = $this->names->getTargetIdentifier(true);
        $attributeName = $this->names->getAttributeWithCollectionName();

        $script .= "
    /**
     * Checks if the $attributeName collection is loaded.
     *
     * @return bool
     */
    public function is{$targetIdentifierPlural}Loaded(): bool
    {
        return \$this->$attributeName !== null;
    }
";
    }

    /**
     * @return string
     */
    protected function getCollectionContentType(): string
    {
        return $this->collectSignature()->buildCombinedType();
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addSetters(string &$script): void
    {
        $attributeScheduledForDeletionVarName = $this->names->getAttributeScheduledForDeletionName();

        $targetIdentifierPlural = $this->names->getTargetIdentifier(true);
        $targetIdentifierSingular = $this->names->getTargetIdentifier(false);

        $inputCollectionVar = '$' . lcfirst($targetIdentifierPlural);
        $collectionContentType = $this->getCollectionContentType();
        [$targetCollectionType, $_] = $this->resolveObjectCollectionClassNameAndType();
        $foreachItem = lcfirst($targetIdentifierSingular);
        $crossRefTableName = $this->crossRelation->getMiddleTable()->getName();
        $attributeName = $this->names->getAttributeWithCollectionName();
        $attributeIsPartialName = $this->names->getAttributeIsPartialName();
        $spreader = $this->setterItemIsArray() ? '...' : '';

        $script .= "
    /**
     * Sets a collection of $targetIdentifierSingular objects related by a many-to-many relationship
     * to the current object by way of the $crossRefTableName cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param \Propel\Runtime\Collection\Collection<$collectionContentType> $inputCollectionVar A Propel collection.
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con Optional connection object
     *
     * @return \$this
     */
    public function set{$targetIdentifierPlural}(Collection $inputCollectionVar, ?ConnectionInterface \$con = null): static
    {
        \$this->clear{$targetIdentifierPlural}();
        \$current{$targetIdentifierPlural} = \$this->get{$targetIdentifierPlural}();

        \${$attributeScheduledForDeletionVarName} = \$current{$targetIdentifierPlural}->diff($inputCollectionVar);

        foreach (\${$attributeScheduledForDeletionVarName} as \$toDelete) {
            \$this->remove{$targetIdentifierSingular}($spreader\$toDelete);
        }

        foreach ($inputCollectionVar as \${$foreachItem}) {
            if (!\$current{$targetIdentifierPlural}->contains(\${$foreachItem})) {
                \$this->doAdd{$targetIdentifierSingular}($spreader\${$foreachItem});
            }
        }

        \$this->{$attributeIsPartialName} = false;
        \$this->$attributeName = $inputCollectionVar instanceof $targetCollectionType
            ? $inputCollectionVar : new $targetCollectionType({$inputCollectionVar}->getData());

        return \$this;
    }
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
        $attributeName = $this->names->getAttributeWithCollectionName();
        $script .= "
        \$this->$attributeName = null;";
    }

    /**
     * @return string
     */
    protected function resolveMiddleQueryClassName(): string
    {
        return $this->resolveClassNameForTable(GeneratorConfig::KEY_QUERY_STUB, $this->crossRelation->getMiddleTable());
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addCount(string &$script): void
    {
        $sourceIdentifierSingular = $this->names->getSourceIdentifier(false);
        $targetIdentifierPlural = $this->names->getTargetIdentifier(true);
        $attributeName = $this->names->getAttributeWithCollectionName();
        $attributeIsPartialName = $this->names->getAttributeIsPartialName();
        $crossRefTableName = $this->crossRelation->getMiddleTable()->getName();
        $targetIdentifierSingular = $this->names->getTargetIdentifier(false);
        $targetQueryClassName = $this->resolveClassNameForTable(GeneratorConfig::KEY_QUERY_STUB, $this->crossRelation->getCrossForeignKeys()[0]->getForeignTable());
        $script .= "
    /**
     * Gets the number of $targetIdentifierSingular objects related by a many-to-many relationship
     * to the current object by way of the $crossRefTableName cross-reference table.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria|null \$criteria Optional query object to filter the query
     * @param bool \$distinct Set to true to force count distinct
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con Optional connection object
     *
     * @return int The number of related $targetIdentifierSingular objects
     */
    public function count{$targetIdentifierPlural}(?Criteria \$criteria = null, bool \$distinct = false, ?ConnectionInterface \$con = null): int
    {
        \$partial = \$this->{$attributeIsPartialName} && !\$this->isNew();
        if (\$this->$attributeName && !\$criteria && !\$partial) {
            return count(\$this->$attributeName);
        }

        if (\$this->isNew() && \$this->$attributeName === null) {
            return 0;
        }

        if (\$partial && !\$criteria) {
            return count(\$this->get$targetIdentifierPlural());
        }

        \$query = $targetQueryClassName::create(null, \$criteria);
        if (\$distinct) {
            \$query->distinct();
        }

        return \$query
            ->filterBy{$sourceIdentifierSingular}(\$this)
            ->count(\$con);
    }
";

        $script .= $this->buildAdditionalCountMethods();
    }

    /**
     * Adds the method that adds an object into the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addAdders(string &$script): void
    {
        $middleTableName = $this->crossRelation->getMiddleTable()->getName();
        $attributeName = $this->names->getAttributeWithCollectionName();

        $targetIdentifierPlural = $this->names->getTargetIdentifier(true);
        $targetIdentifierSingular = $this->names->getTargetIdentifier(false);

        $adderArgs = $this->collectSignature()->buildFunctionParameterVariables();
        $collectionArg = ($this->setterItemIsArray()) ? "[$adderArgs]" : $adderArgs;

        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            $targetRelationIdentifier = $this->nameProducer->resolveRelationIdentifier($fk);
            [$methodSignature, $_, $phpDoc] = $this->collectSignatureWithFirstArgument($fk)->buildFullSignature();

            $script .= "
    /**
     * Associate a $targetRelationIdentifier with this object through the $middleTableName cross reference table.
     *$phpDoc
     *
     * @return static
     */
    public function add{$targetRelationIdentifier}($methodSignature): static
    {
        if (\$this->$attributeName === null) {
            \$this->init{$targetIdentifierPlural}();
        }

        if (!\$this->get{$targetIdentifierPlural}()->contains($collectionArg)) {
            // only add it if the **same** object is not already associated
            \$this->{$attributeName}->push($collectionArg);
            \$this->doAdd{$targetIdentifierSingular}($adderArgs);
        }

        return \$this;
    }
";
        }
    }

    /**
     * Adds the method that remove an object from the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addRemove(string &$script): void
    {
        $localAttributeName = $this->names->getAttributeWithCollectionName();
        $deletionScheduledAttributeName = $this->names->getAttributeScheduledForDeletionName();

        $targetIdentifierPlural = $this->names->getTargetIdentifier(true);
        $targetIdentifierSingular = $this->names->getTargetIdentifier(false);

        [$signature, $inputArgs, $paramDoc] = $this->collectSignature()->buildFullSignature();
        $names = str_replace('$', '', $inputArgs);

        $middleTableName = $this->crossRelation->getMiddleTable();
        $middleModelClassName = $this->names->getMiddleModelClassName();
        $middleTableIdentifierSingular = $this->names->getMiddleTableIdentifier(false);
        $middleModelName = '$' . $middleTableName->getCamelCaseName();
        $sourceIdentifierSingular = $this->names->getSourceIdentifier(false);

        if ($this->setterItemIsArray()) {
            $inputArgs = "[$inputArgs]";
        }

        $script .= "
    /**
     * Remove $names of this object through the {$middleTableName->getName()} cross reference table.
     *$paramDoc
     *
     * @return static
     */
    public function remove{$targetIdentifierSingular}($signature): static
    {
        if (!\$this->get{$targetIdentifierPlural}()->contains({$inputArgs})) {
            return \$this;
        }

        {$middleModelName} = new {$middleModelClassName}();";

        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            $concreteTargetIdentifierSingular = $this->nameProducer->resolveRelationIdentifier($fk, false);
            $targetVar = lcfirst($concreteTargetIdentifierSingular);
            $getterName = $this->concatRelationTargetNames($fk);
            $middleTableArgsCsv = $this->buildMiddleTableArgumentCsv($fk);
            if ($this->setterItemIsArray()) {
                $middleTableArgsCsv = "[$middleTableArgsCsv]";
            }

            $script .= "
        {$middleModelName}->set{$concreteTargetIdentifierSingular}(\${$targetVar});
        if (\${$targetVar}->is{$getterName}Loaded()) {
            //remove the back reference if available
            \${$targetVar}->get$getterName()->removeObject($middleTableArgsCsv);
        }\n";
        }

        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $primaryKey) {
            $paramName = lcfirst($primaryKey->getPhpName());
            $script .= "
        {$middleModelName}->set{$primaryKey->getPhpName()}(\$$paramName);";
        }

        $script .= "
        {$middleModelName}->set{$sourceIdentifierSingular}(\$this);
        \$this->remove{$middleTableIdentifierSingular}(clone {$middleModelName});
        {$middleModelName}->clear();

        \$this->{$localAttributeName}->remove(\$this->{$localAttributeName}->search({$inputArgs}));

        if (\$this->{$deletionScheduledAttributeName} === null) {
            \$this->{$deletionScheduledAttributeName} = clone \$this->{$localAttributeName};
            \$this->{$deletionScheduledAttributeName}->clear();
        }

        \$this->{$deletionScheduledAttributeName}->push({$inputArgs});

        return \$this;
    }
";
    }
}
