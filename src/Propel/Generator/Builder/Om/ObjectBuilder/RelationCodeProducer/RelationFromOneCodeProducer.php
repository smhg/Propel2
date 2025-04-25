<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

/**
 * A one-to-one relation from an incoming FK
 */
class RelationFromOneCodeProducer extends AbstractIncomingRelationCode
{
    /**
     * Constructs variable name for fkey-related objects.
     *
     * @return string
     */
    #[\Override]
    public function getAttributeName(): string
    {
        return 'single' . $this->relation->getIdentifierReversed();
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

        $this->addGet($script);
        $this->addSet($script);
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
    #[\Override]
    public function addAttributes(string &$script): void
    {
        $className = $this->getClassNameFromTable($this->relation->getTable());

        $script .= "
    /**
     * @var        $className|null one-to-one related $className object
     */
    protected $" . $this->getAttributeName() . ";
";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addDeleteScheduledItemsCode(string &$script): void
    {
        $varName = $this->getAttributeName();
        $script .= "
        if (\$this->$varName !== null) {
            if (!\$this->{$varName}->isDeleted() && (\$this->{$varName}->isNew() || \$this->{$varName}->isModified())) {
                \$affectedRows += \$this->{$varName}->save(\$con);
            }
        }\n";
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
        if (\$this->$varName) {
            \$this->{$varName}->clearAllReferences(\$deep);
        }";

        return $varName;
    }

    /**
     * Adds the method that gets a one-to-one related referrer fkey.
     * This is for one-to-one relationship special case.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGet(string &$script): void
    {
        $refFK = $this->relation;
        $className = $this->getClassNameFromTable($refFK->getTable());

        $queryClassName = $this->getClassNameFromBuilder($this->getNewStubQueryBuilder($refFK->getTable()));

        $varName = $this->getAttributeName();

        $script .= "
    /**
     * Gets a single $className object, which is related to this object by a one-to-one relationship.
     *
     * @param ConnectionInterface \$con optional connection object
     * @return $className|null
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function get" . $this->getRefFKPhpNameAffix($refFK, false) . "(?ConnectionInterface \$con = null)
    {
";
        $script .= "
        if (\$this->$varName === null && !\$this->isNew()) {
            \$this->$varName = $queryClassName::create()->findPk(\$this->getPrimaryKey(), \$con);
        }

        return \$this->$varName;
    }
";
    }

    /**
     * Adds the method that sets a one-to-one related referrer fkey.
     * This is for one-to-one relationships special case.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addSet(string &$script): void
    {
        $refFK = $this->relation;
        $className = $this->getClassNameFromTable($refFK->getTable());

        $varName = $this->getAttributeName();

        $script .= "
    /**
     * Sets a single $className object as related to this object by a one-to-one relationship.
     *
     * @param $className|null \$v $className
     * @return \$this The current object (for fluent API support)
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function set" . $this->getRefFKPhpNameAffix($refFK, false) . "(?$className \$v = null)
    {
        \$this->$varName = \$v;

        // Make sure that that the passed-in $className isn't already associated with this object
        if (\$v !== null && \$v->get" . $refFK->getIdentifier() . "(null, false) === null) {
            \$v->set" . $refFK->getIdentifier() . "(\$this);
        }

        return \$this;
    }
";
    }
}
