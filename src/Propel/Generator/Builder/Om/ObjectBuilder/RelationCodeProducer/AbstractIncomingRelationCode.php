<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use LogicException;
use Propel\Common\Pluralizer\PluralizerInterface;
use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Model\ForeignKey;

/**
 * An incoming relation  ("refFK"), to a single row (incoming one-to-one)
 * or one-to-many.
 */
abstract class AbstractIncomingRelationCode extends AbstractRelationCodeProducer
{
    /**
     * @var ForeignKey
     */
    protected $relation;

    /**
     * @param \Propel\Generator\Model\ForeignKey $crossRelation
     * @param \Propel\Generator\Builder\Om\ObjectBuilder $parentBuilder
     *
     * @throws \LogicException
     */
    protected function __construct(ForeignKey $relation, ObjectBuilder $parentBuilder)
    {
        $this->relation = $relation;
        parent::__construct($relation->getForeignTable(), $parentBuilder);
    }

    public static function create(ForeignKey $relation, ObjectBuilder $parentBuilder): AbstractIncomingRelationCode
    {
        return $relation->isLocalPrimaryKey()
            ? new RelationFromOneCodeProducer($relation, $parentBuilder)
            : new OneToManyRelationCodeProducer($relation, $parentBuilder);
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk The referrer FK that we need a name for.
     * @param bool $plural Whether the php name should be plural (e.g. initRelatedObjs() vs. addRelatedObj()
     *
     * @return string|null
     */
    public function getRefFKPhpNameAffix(ForeignKey $fk, bool $plural = false): ?string
    {
        return $fk->getIdentifierReversed($plural ? $this->getPluralizer() : null);
    }

    /**
     * Constructs variable name for fkey-related objects.
     *
     * @return string
     */
    abstract public function getAttributeName(): string;

    /**
     * @return void
     */
    public function registerTargetClasses(): void
    {
        $targetTable = $this->relation->getTable();
        $this->declareClassFromBuilder($this->getNewStubObjectBuilder($targetTable), 'Child');
        $this->declareClassFromBuilder($this->getNewStubQueryBuilder($targetTable));
        
    }

    /**
     * @param string $script
     * @param array<ForeignKey> $referrers
     * @param \Propel\Common\Pluralizer\PluralizerInterface $pluralizer
     *
     * @return void
     */
    public static function addInitRelations(string &$script, array $referrers, PluralizerInterface $pluralizer): void
    {
        $script .= "

    /**
     * Initializes a collection based on the name of a relation.
     * Avoids crafting an 'init[\$relationName]s' method name
     * that wouldn't work when StandardEnglishPluralizer is used.
     *
     * @param string \$relationName The name of the relation to initialize
     * @return void
     */
    public function initRelation(\$relationName): void
    {";
        foreach ($referrers as $refFK) {
            if ($refFK->isLocalPrimaryKey()) {
                continue;
            }
            
            $relationIdentifierSingular = $refFK->getIdentifierReversed();
            $relationIdentifierPlural = $refFK->getIdentifierReversed($pluralizer);

            $script .= "
        if ('$relationIdentifierSingular' === \$relationName) {
            \$this->init$relationIdentifierPlural();
            return;
        }";
        }
        $script .= "
    }\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    public function addOnReloadCode(string &$script): void
    {
        $attributeName = $this->getAttributeName();

        $script .= "
    \$this->$attributeName = null;\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    public function addScheduledForDeletionAttribute(string &$script): void
    {
        $refFK = $this->relation;
        $className = $this->getClassNameFromTable($refFK->getTable());
        $fkName = lcfirst($this->getRefFKPhpNameAffix($refFK, true));

        $script .= "
    /**
     * @var ObjectCollection|{$className}[]
     */
    protected \${$fkName}ScheduledForDeletion = null;\n";
    }
}
