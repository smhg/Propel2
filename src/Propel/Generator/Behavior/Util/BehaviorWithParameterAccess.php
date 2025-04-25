<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Behavior\Util;

use Propel\Generator\Exception\SchemaException;
use Propel\Generator\Model\Behavior;

abstract class BehaviorWithParameterAccess extends Behavior
{
    /**
     * @template ReturnType of string|null
     *
     * @param string $name
     * @param ReturnType|string|null $defaultValue
     *
     * @return ReturnType|string|null
     */
    #[\Override]
    public function getParameter(string $name, ?string $defaultValue = null): ?string
    {
        $val = $this->parameters[$name] ?? null;

        return is_string($val) ? trim($val) : $defaultValue; // means empty space (' ') cannot be a value, seems ok.
    }

    /**
     * @template ReturnType of bool|null
     *
     * @param string $parameterName
     * @param ReturnType|bool|null $defaultValue
     *
     * @return ReturnType|bool|null
     */
    public function getParameterBool(string $parameterName, ?bool $defaultValue = null): ?bool
    {
        $val = $this->getParameter($parameterName);

        return !$val ? $defaultValue : in_array(strtolower($val), ['true', '1']);
    }

    /**
     * @template ReturnType of int|null
     *
     * @param string $parameterName
     * @param ReturnType|int|null $defaultValue
     *
     * @throws \Propel\Generator\Exception\SchemaException
     *
     * @return ReturnType|int|null
     */
    public function getParameterInt(string $parameterName, ?int $defaultValue = null): ?int
    {
        $val = $this->getParameter($parameterName);
        if ($val === null) {
            return $defaultValue;
        }
        if (!is_numeric($val)) {
            throw new SchemaException("Parameter $parameterName should be numeric, but is '$val'");
        }

        return (int)$val;
    }

    /**
     * @template ReturnType of array|null
     *
     * @param string $parameterName
     * @param ReturnType|array|null $defaultValue
     * @param callable|null $mapper
     *
     * @return ReturnType|array|null
     */
    public function getParameterCsv(string $parameterName, ?array $defaultValue = [], ?callable $mapper = null): ?array
    {
        $valString = $this->getParameter($parameterName);
        if (!$valString) {
            return $defaultValue;
        }
        $valList = $this->explodeCsv($valString);

        return $mapper ? array_map($mapper, $valList) : $valList;
    }

    /**
     * @param string $parameterName
     * @param mixed $defaultValue
     *
     * @return mixed|true
     */
    public function getParameterTrueOrValue(string $parameterName, $defaultValue = null)
    {
        $val = $this->parameters[$parameterName] ?? null;
        $isTrue = is_string($val) && $this->getParameterBool($parameterName);

        return $isTrue ?: ($this->parameters[$parameterName] ?? $defaultValue);
    }

    /**
     * @template ReturnType of array|null
     *
     * @param string $parameterName
     * @param ReturnType|array|null $defaultValue
     * @param callable|null $mapper
     *
     * @return ReturnType|array|true
     */
    public function getParameterTrueOrCsv(string $parameterName, ?array $defaultValue = null, $mapper = null)
    {
        $val = $this->getParameterBool($parameterName);

        return $val ?: $this->getParameterCsv($parameterName, $defaultValue, $mapper);
    }

    /**
     * @param string $stringValue
     *
     * @return array<string>|null
     */
    protected function explodeCsv(string $stringValue): ?array
    {
        $stringValue = trim($stringValue);

        return trim($stringValue) ? array_map('trim', explode(',', $stringValue)) : null;
    }

    /**
     * Unwraps an array created by `<parameter-list>`.
     *
     * @psalm-param [array]|array<mixed> $parameterListOrList
     *
     * @param array $parameterListOrList
     *
     * @return array
     */
    protected function unwrapParameterList(array $parameterListOrList): array
    {
        $firstElement = reset($parameterListOrList);
        $assumeParameterList = count($parameterListOrList) === 1 && is_array($firstElement);

        return $assumeParameterList ? $firstElement : $parameterListOrList;
    }
}
