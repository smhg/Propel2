<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder;

use Propel\Generator\Builder\Om\NameProducer;
use Propel\Generator\Builder\Om\ReferencedClasses;
use Propel\Generator\Model\CrossForeignKeys;

class CrossRelationNames
{
    /**
     * @var \Propel\Generator\Model\CrossForeignKeys
     */
    protected $crossRelation;

    /**
     * @var \Propel\Generator\Builder\Om\NameProducer
     */
    protected $nameProducer;

    /**
     * @var \Propel\Generator\Builder\Om\ReferencedClasses
     */
    protected $referencedClasses;

    /**
     * @var string
     */
    protected $attributePrefix;

    /**
     * @var string
     */
    protected $attributeWithCollectionName;

    /**
     * @var string
     */
    protected $attributeWithPartialCollectionName;

    /**
     * @var string
     */
    protected $attributeScheduledForDeletionName;

    /**
     * @param \Propel\Generator\Model\CrossForeignKeys $crossRelation
     * @param string $attributePrefix
     * @param \Propel\Generator\Builder\Om\NameProducer $nameProducer
     * @param \Propel\Generator\Builder\Om\ReferencedClasses $referencedClasses
     */
    public function __construct(
        CrossForeignKeys $crossRelation,
        string $attributePrefix,
        NameProducer $nameProducer,
        ReferencedClasses $referencedClasses
    ) {
        $this->crossRelation = $crossRelation;
        $this->attributePrefix = $attributePrefix;
        $this->referencedClasses = $referencedClasses;
        $this->nameProducer = $nameProducer;

        $this->initNames();
    }

    /**
     * @return void
     */
    protected function initNames(): void
    {
        $targetIdentifier = $this->getTargetIdentifier(true);
        $this->attributeWithCollectionName = $this->attributePrefix . $targetIdentifier;
        $this->attributeWithPartialCollectionName = $this->attributePrefix . $targetIdentifier . 'IsPartial';
        $this->attributeScheduledForDeletionName = lcfirst($targetIdentifier) . 'ScheduledForDeletion';
    }

    /**
     * @return string
     */
    public function getAttributeWithCollectionName(): string
    {
        return $this->attributeWithCollectionName;
    }

    /**
     * @return string
     */
    public function getAttributeIsPartialName(): string
    {
        return $this->attributeWithPartialCollectionName;
    }

    /**
     * @return string
     */
    public function getAttributeScheduledForDeletionName(): string
    {
        return $this->attributeScheduledForDeletionName;
    }

    /**
     * @return string
     */
    public function getMiddleTableModelClass(): string
    {
        return $this->referencedClasses->getInternalNameOfTable($this->crossRelation->getMiddleTable());
    }

    /**
     * @param bool $plural
     *
     * @return string
     */
    public function getMiddleTableIdentifier(bool $plural): string
    {
        return $this->nameProducer->buildForeignKeyBackReferenceNameAffix($this->crossRelation->getIncomingForeignKey(), $plural);
    }

    /**
     * @return string
     */
    public function getMiddleModelClassName(): string
    {
        return $this->referencedClasses->getInternalNameOfTable($this->crossRelation->getMiddleTable());
    }

    /**
     * @param bool $plural
     * @param bool $lowercased
     *
     * @return string
     */
    public function getTargetIdentifier(bool $plural, bool $lowercased = false): string
    {
        return $this->resolveRelationForwardName($plural, $lowercased);
    }

    /**
     * @param bool $plural
     * @param bool $lowercased
     *
     * @return string
     */
    public function getSourceIdentifier(bool $plural, bool $lowercased = false): string
    {
        return $this->nameProducer->resolveRelationIdentifier($this->crossRelation->getIncomingForeignKey(), $plural, $lowercased);
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
        $relationName = $this->concatKeyNames($plural);

        return $lowercased ? lcfirst($relationName) : $relationName;
    }

    /**
     * @param bool $plural
     *
     * @return string
     */
    protected function concatKeyNames(bool $plural = true): string
    {
        $name = '';
        $fks = $this->crossRelation->getCrossForeignKeys();
        $unclassifiedPrimaryKeys = $this->crossRelation->getUnclassifiedPrimaryKeys();

        $pluralizeAtIndex = $plural && !$unclassifiedPrimaryKeys ? count($fks) - 1 : -1;
        foreach ($fks as $ix => $fk) {
            $name .= $this->nameProducer->resolveRelationIdentifier($fk, $ix === $pluralizeAtIndex);
        }

        if (!$unclassifiedPrimaryKeys) {
            return $name;
        }

        foreach ($unclassifiedPrimaryKeys as $pk) {
            $name .= $pk->getPhpName();
        }

        return $plural ? $this->nameProducer->getPluralizer()->getPluralForm($name) : $name;
    }
}
