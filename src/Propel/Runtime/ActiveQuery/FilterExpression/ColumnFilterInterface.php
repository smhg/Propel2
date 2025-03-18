<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\Adapter\AdapterInterface;

interface ColumnFilterInterface
{
    /**
     * Builds the filter expression literal and adds its parameter data to the input buffer
     *
     * @param array<string, array{table: string, column: string, value: mixed}> $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @return string
     */
    public function buildStatement(array &$paramCollector): string;

    /**
     * Build parameters, possibly without building statement.
     *
     * Used for example when using query-cache behavior.
     *
     * Basic implementation does not avoid building the statement.
     *
     * @param array<string, array{table: string, column: string, value: mixed}> $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @return void
     */
    public function collectParameters(array &$paramCollector): void;

    /**
     * @return string
     */
    public function getLocalColumnName(): string;

    /**
     * @return string|null
     */
    public function getTableAlias(): ?string;

    /**
     * @return string
     */
    public function getOperator(): string;

    /**
     * @return bool
     */
    public function hasValue(): bool;

    /**
     * @return mixed
     */
    public function getValue();

    /**
     * @return string
     */
    public function __toString(): string;

    /**
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface $filter
     *
     * @return bool
     */
    public function equals(ColumnFilterInterface $filter): bool;

    /**
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface $filter
     *
     * @return static
     */
    public function addAnd(ColumnFilterInterface $filter);

    /**
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface $filter
     *
     * @return static
     */
    public function addOr(ColumnFilterInterface $filter);

    /**
     * @return array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    public function getAttachedFilter(): array;

    /**
     * @param \Propel\Runtime\Adapter\AdapterInterface $adapter
     *
     * @return void
     */
    public function setAdapter(AdapterInterface $adapter): void;
}
