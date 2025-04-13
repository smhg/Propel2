<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om;

use Propel\Common\Pluralizer\PluralizerInterface;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\ForeignKey;

class NameProducer
{
    /**
     * The Pluralizer class to use.
     *
     * @var \Propel\Common\Pluralizer\PluralizerInterface
     */
    protected PluralizerInterface $pluralizer;

    /**
     * @param \Propel\Common\Pluralizer\PluralizerInterface $pluralizer
     */
    public function __construct(PluralizerInterface $pluralizer)
    {
        $this->pluralizer = $pluralizer;
    }

    /**
     * Returns new or existing Pluralizer class.
     *
     * @return \Propel\Common\Pluralizer\PluralizerInterface
     */
    public function getPluralizer(): PluralizerInterface
    {
        return $this->pluralizer;
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public function toPluralName(string $str): string
    {
        return $this->pluralizer->getPluralForm($str);
    }

    /**
     * Builds the PHP method name affix to be used for foreign keys for the current table (not referrers to this table).
     *
     * The difference between this method and the getRefFKPhpNameAffix() method is that in this method the
     * classname in the affix is the foreign table classname.
     *
     * @param \Propel\Generator\Model\ForeignKey $fk The local FK that we need a name for.
     * @param bool $plural Whether the php name should be plural (e.g. initRelatedObjs() vs. addRelatedObj()
     * @param bool $lcfirst
     *
     * @return string
     */
    public function resolveRelationForwardName(ForeignKey $fk, bool $plural = false, bool $lcfirst = false): string
    {
        //old name: getFKPhpNameAffix
        
        if ($fk->getPhpName() !== null) {
            $name = $fk->getPhpName();
            if ($plural) {
                $name = $this->pluralizer->getPluralForm($name);
            }
        } else {
            $className = $fk->getForeignTableOrFail()->getPhpName();
            if ($plural) {
                $className = $this->pluralizer->getPluralForm($className);
            }
    
            $name = $className . static::buildForeignKeyRelatedByNameSuffix($fk);
        }

        return $lcfirst ? lcfirst($name) : $name;
    }

    /**
     * Gets the "RelatedBy*" suffix (if needed) that is attached to method and variable names.
     *
     * The related by suffix is based on the local columns of the foreign key. If there is more than
     * one column in a table that points to the same foreign table, then a 'RelatedByLocalColName' suffix
     * will be appended.
     *
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    public static function buildForeignKeyRelatedByNameSuffix(ForeignKey $fk): string
    {
        // old name: getRelatedBySuffix

        $relCol = '';

        foreach ($fk->getMapping() as $mapping) {
            [$localColumn, $foreignValueOrColumn] = $mapping;
            $localTable = $fk->getTable();

            $tableName = $fk->getTableName();
            $foreignTableName = (string)$fk->getForeignTableName();
            if (
                count($localTable->getForeignKeysReferencingTable($foreignTableName)) > 1
                || count($fk->getForeignTableOrFail()->getForeignKeysReferencingTable($tableName)) > 0
                || $foreignTableName === $tableName
            ) {
                // self referential foreign key, or several foreign keys to the same table, or cross-reference fkey
                $relCol .= $localColumn->getPhpName();
            }
        }

        if ($relCol) {
            $relCol = 'RelatedBy' . $relCol;
        }

        return $relCol;
    }

    /**
     * Gets the PHP method name affix to be used for referencing foreign key methods and variable names (e.g. set????(), $coll???).
     *
     * The difference between this method and the getFKPhpNameAffix() method is that in this method the
     * classname in the affix is the classname of the local fkey table.
     *
     * @param \Propel\Generator\Model\ForeignKey $fk The referrer FK that we need a name for.
     * @param bool $plural Whether the php name should be plural (e.g. initRelatedObjs() vs. addRelatedObj()
     *
     * @return string|null
     */
    public function buildForeignKeyBackReferenceNameAffix(ForeignKey $fk, bool $plural = false): ?string
    {
        // old name: getRefFKPhpNameAffix

        if ($fk->getRefPhpName()) {
            return $plural
                ? $this->pluralizer->getPluralForm($fk->getRefPhpName())
                : $fk->getRefPhpName();
        }

        $className = $fk->getTable()->getPhpName();
        if ($plural) {
            $className = $this->pluralizer->getPluralForm($className);
        }

        return $className . static::buildForeignKeyBackReferenceRelatedBySuffix($fk);
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    public static function buildForeignKeyBackReferenceRelatedBySuffix(ForeignKey $fk): string
    {
        // old name: getRefRelatedBySuffix

        $relCol = '';
        foreach ($fk->getMapping() as $mapping) {
            [$localColumn, $foreignValueOrColumn] = $mapping;
            $localTable = $fk->getTable();

            $tableName = $fk->getTableName();
            $foreignTableName = (string)$fk->getForeignTableName();
            $foreignKeysToForeignTable = $localTable->getForeignKeysReferencingTable($foreignTableName);
            if ($foreignValueOrColumn instanceof Column && $foreignTableName === $tableName) {
                $foreignColumnName = $foreignValueOrColumn->getPhpName();
                // self referential foreign key
                $relCol .= $foreignColumnName;
                if (count($foreignKeysToForeignTable) > 1) {
                    // several self-referential foreign keys
                    $relCol .= array_search($fk, $foreignKeysToForeignTable);
                }
            } elseif (count($foreignKeysToForeignTable) > 1 || count($fk->getForeignTableOrFail()->getForeignKeysReferencingTable($tableName)) > 0) {
                // several foreign keys to the same table, or symmetrical foreign key in foreign table
                $relCol .= $localColumn->getPhpName();
            }
        }

        if ($relCol) {
            $relCol = 'RelatedBy' . $relCol;
        }

        return $relCol;
    }
}
