<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Model\Column;

class FunctionArgumentSignatureCollector
{
    /**
     * Method argument value for {@see static::addColumn()} to add
     * column default values to generated argument declarations.
     *
     * @var string
     */
    public const USE_COLUMN_DEFAULT = 'col';

    /**
     * Method argument value for {@see static::addColumn()} to add
     * null as default value to generated argument declarations.
     *
     * @var string
     */
    public const USE_DEFAULT_NULL = 'null';

    /**
     * Argument declarations, possibly with type hint and default value
     * i.e. '?int $id = null' or '$id'.
     *
     * @var array<string>
     */
    protected array $argumentDeclarations = [];

    /**
     * Argument declarations without type hint '$id'
     *
     * @var array<string>
     */
    protected array $functionParameterVariables = [];

    /**
     * Param declarations for phpdoc.
     *
     * @var array<string>
     */
    protected array $phpDocParamDeclaration = [];

    /**
     * @var array<string>
     */
    protected array $classNames = [];

    /**
     * @param \Propel\Generator\Model\Column $column
     * @param string|null $withDefaultValue Set to {@see static::USE_COLUMN_DEFAULT} or {@see static::USE_DEFAULT_NULL} to add default values to argument declarations.
     *
     * @return void
     */
    public function addColumn(Column $column, ?string $withDefaultValue = null): void
    {
        $name = '$' . lcfirst($column->getPhpName());
        $phpType = $column->getPhpType();
        $typeHint = $column->isPhpArrayType() ? 'array' : null;
        $defaultValue = $this->resolveDefaultValue($column, $withDefaultValue);

        $this->addEntry($name, $phpType, $typeHint, $defaultValue);
    }

    /**
     * @param \Propel\Generator\Model\Column $column
     * @param string|null $withDefaultValue
     *
     * @return string|null
     */
    protected function resolveDefaultValue(Column $column, ?string $withDefaultValue): ?string
    {
        switch ($withDefaultValue) {
            case self::USE_COLUMN_DEFAULT:
                return $column->getDefaultValueString();
            case self::USE_DEFAULT_NULL:
                return 'null';
            default:
                return null;
        }
    }

    /**
     * @param string $argumentName
     * @param string $phpType
     * @param string|null $typeHint
     * @param string|null $defaultValue
     *
     * @return void
     */
    public function addEntry(string $argumentName, string $phpType, ?string $typeHint = null, ?string $defaultValue = null): void
    {
        if ($defaultValue === 'null' && $typeHint) {
            $typeHint = "?$typeHint";
        }
        $this->argumentDeclarations[] = $this->buildArgumentDeclarationStatement($argumentName, $phpType, $defaultValue);
        $this->functionParameterVariables[] = $argumentName;
        $docType = $this->buildDocType($phpType, $typeHint, $defaultValue);
        $this->phpDocParamDeclaration[] = "\n     * @param $docType $argumentName";
        $this->classNames[] = $phpType;
    }

    /**
     * Build statement like "?int $id = null"
     *
     * @param string $argumentName
     * @param string|null $phpType
     * @param string|null $defaultValue
     *
     * @return string
     */
    protected function buildArgumentDeclarationStatement(string $argumentName, ?string $phpType, ?string $defaultValue): string
    {
        $argumentDeclarationStatement = '';

        if ($phpType) {
            if ($defaultValue === 'null') {
                $argumentDeclarationStatement .= '?';
            }
            $argumentDeclarationStatement .= "$phpType ";
        }

        $argumentDeclarationStatement .= $argumentName;

        if ($defaultValue !== null) {
            $argumentDeclarationStatement .= " = $defaultValue";
        }

        return $argumentDeclarationStatement;
    }

    /**
     * @param string $phpType
     * @param string|null $typeHint
     * @param string|null $defaultValue
     *
     * @return string
     */
    protected function buildDocType(string $phpType, ?string $typeHint, ?string $defaultValue): string
    {
        $docType = $typeHint === 'array' ? "array<$phpType>" : $phpType;
        if ($docType && $defaultValue === 'null') {
            $docType .= '|null';
        }

        return $docType;
    }

    /**
     * @param string $glue
     * @param string|null $pre
     * @param string|null $post
     *
     * @return string
     */
    public function buildParameterDeclaration(string $glue = ', ', ?string $pre = null, ?string $post = null): string
    {
        $elements = $this->argumentDeclarations;
        if ($pre || $post) {
            $pre = $pre ? trim($pre) . ' ' : '';
            $post = $post ? ' ' . trim($post) : '';
            $elements = array_map(fn ($declaration) => "$pre$declaration$post", $elements);
        }

        return implode($glue, $elements);
    }

    /**
     * @return string
     */
    public function buildPhpDocParamDeclaration(): string
    {
        return implode('', $this->phpDocParamDeclaration);
    }

    /**
     * @param string $glue
     *
     * @return string
     */
    public function buildFunctionParameterVariables(string $glue = ', '): string
    {
        return implode($glue, $this->functionParameterVariables);
    }

    /**
     * @return array{string, string, string}
     */
    public function buildFullSignature(): array
    {
        return [
            $this->buildParameterDeclaration(),
            $this->buildFunctionParameterVariables(),
            $this->buildPhpDocParamDeclaration(),
        ];
    }

    /**
     * @param string $glue
     *
     * @return string
     */
    public function buildCombinedType(string $glue = ', '): string
    {
        return count($this->classNames) <= 1
            ? (reset($this->classNames) ?: '')
            : 'array{' . implode($glue, $this->classNames) . '}';
    }
}
