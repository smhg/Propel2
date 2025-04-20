    /**
     * The (dot-path) name of this class
     */
    public const CLASS_NAME = '<?= $className ?>';

    /**
     * The default database name for this class
     */
    public const DATABASE_NAME = '<?= $dbName ?>';

    /**
     * The table name for this class
     */
    public const TABLE_NAME = '<?= $tableName ?>';

    /**
     * The PHP name of this class (PascalCase)
     */
    public const TABLE_PHP_NAME = '<?= $tablePhpName ?>';

    /**
     * The related Propel class for this table
     */
    public const OM_CLASS = '<?= $omClassName ?>';

    /**
     * A class that can be returned by this tableMap
     */
    public const CLASS_DEFAULT = '<?= $classPath ?>';

    /**
     * The total number of columns
     */
    public const NUM_COLUMNS = <?= $nbColumns ?>;

    /**
     * The number of lazy-loaded columns
     */
    public const NUM_LAZY_LOAD_COLUMNS = <?= $nbLazyLoadColumns ?>;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    public const NUM_HYDRATE_COLUMNS = <?= $nbHydrateColumns ?>;
<?php foreach ($columns as $col) : ?>

    /**
     * the column name for the <?= $col->getName() ?> field
     */
    public const <?= $col->getConstantName() ?> = '<?= $tableName ?>.<?= $col->getName() ?>';
<?php endforeach; ?>

    /**
     * The default string format for model objects of the related table
     */
    public const DEFAULT_STRING_FORMAT = '<?= $stringFormat ?>';

    /**
     * @var class-string<<?= $objectCollectionType ?>>
     */
    public const DEFAULT_OBJECT_COLLECTION = '<?= $objectCollectionClassName ?>';
