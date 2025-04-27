<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Builder\Om\ReferencedClasses;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\CrossRelation;
use Propel\Generator\Model\ForeignKey;

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
     * @var \Propel\Generator\Builder\Om\ReferencedClasses
     */
    protected ReferencedClasses $referencedClasses;

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
     * @param \Propel\Generator\Builder\Om\ReferencedClasses $referencedClasses
     */
    public function __construct(ReferencedClasses $referencedClasses)
    {
        $this->referencedClasses = $referencedClasses;
    }

    /**
     * @param \Propel\Generator\Builder\Om\ReferencedClasses $referencedClasses
     *
     * @return self
     */
    public static function create(ReferencedClasses $referencedClasses): self
    {
        return new self($referencedClasses);
    }

    /**
     * Collect signature from keys, but the supplied Fk first.
     *
     * @param \Propel\Generator\Model\ForeignKey $firstFk
     * @param \Propel\Generator\Model\CrossRelation $crossRelation
     *
     * @return static
     */
    public function collectWithFirstArgument(ForeignKey $firstFk, CrossRelation $crossRelation): self
    {
        $this->addRelationTarget($firstFk);

        return $this->collect($crossRelation, $firstFk);
    }

    /**
     * Collect signature from keys.
     *
     * @param \Propel\Generator\Model\CrossRelation $crossRelation
     * @param \Propel\Generator\Model\ForeignKey|null $fkToIgnore
     * @param string|null $withDefaultValue Set to {@see FunctionArgumentSignatureCollector::USE_COLUMN_DEFAULT} or {@see FunctionArgumentSignatureCollector::USE_DEFAULT_NULL} to add default values to argument declarations.
     *
     * @return $this
     */
    public function collect(
        CrossRelation $crossRelation,
        ?ForeignKey $fkToIgnore = null,
        ?string $withDefaultValue = null
    ) {
        foreach ($crossRelation->getCrossForeignKeys() as $fk) {
            if ($fk === $fkToIgnore) {
                continue;
            }
            $this->addRelationTarget($fk);
        }

        foreach ($crossRelation->getUnclassifiedPrimaryKeys() as $column) {
            //we need to add all those $primaryKey s as additional parameter as they are needed
            //to create the entry in the middle-table.
            $this->addColumn($column, $withDefaultValue);
        }

        return $this;
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $relation
     *
     * @return void
     */
    protected function addRelationTarget(ForeignKey $relation): void
    {
        $argumentName = '$' . lcfirst($relation->getIdentifier());
        $phpTypeHint = $this->referencedClasses->resolveForeignKeyTargetModelClassName($relation);
        $qualifiedType = $this->referencedClasses->resolveQualifiedModelClassNameForTable($relation->getForeignTableOrFail());

        $this->addEntry($argumentName, $phpTypeHint, $qualifiedType);
    }

    /**
     * @param \Propel\Generator\Model\Column $column
     * @param string|null $withDefaultValue Set to {@see static::USE_COLUMN_DEFAULT} or {@see static::USE_DEFAULT_NULL} to add default values to argument declarations.
     *
     * @return void
     */
    public function addColumn(Column $column, ?string $withDefaultValue = null): void
    {
        $argumentName = '$' . lcfirst($column->getPhpName());
        $phpType = $qualifiedType = $column->getPhpType();
        $defaultValue = $this->resolveDefaultValue($column, $withDefaultValue);

        $this->addEntry($argumentName, $phpType, $qualifiedType, $defaultValue);
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
     * @param string $phpTypeHint
     * @param string $qualifiedType
     * @param string|null $defaultValue
     *
     * @return void
     */
    protected function addEntry(
        string $argumentName,
        string $phpTypeHint,
        string $qualifiedType,
        ?string $defaultValue = null
    ): void {
        $this->argumentDeclarations[] = $this->buildArgumentDeclarationStatement($argumentName, $phpTypeHint, $defaultValue);
        $this->functionParameterVariables[] = $argumentName;

        $docType = $defaultValue === 'null' ? "$qualifiedType|null" : $qualifiedType;
        $this->phpDocParamDeclaration[] = "\n     * @param $docType $argumentName";

        $this->classNames[] = $docType;
    }

    /**
     * Build statement like "?int $id = null"
     *
     * @param string $argumentName
     * @param string|null $phpTypeHint
     * @param string|null $defaultValue
     *
     * @return string
     */
    protected function buildArgumentDeclarationStatement(string $argumentName, ?string $phpTypeHint, ?string $defaultValue): string
    {
        $argumentDeclarationStatement = '';

        if ($phpTypeHint) {
            if ($defaultValue === 'null') {
                $argumentDeclarationStatement .= '?';
            }
            $argumentDeclarationStatement .= "$phpTypeHint ";
        }

        $argumentDeclarationStatement .= $argumentName;

        if ($defaultValue !== null) {
            $argumentDeclarationStatement .= " = $defaultValue";
        }

        return $argumentDeclarationStatement;
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
