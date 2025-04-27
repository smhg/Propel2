
/**
 * Updates the aggregate columns defined as <?= $aggregationName ?>
 *
 * @param \Propel\Runtime\Connection\ConnectionInterface $con A connection object
 */
public function update<?= $aggregationName ?>(ConnectionInterface $con)
{
    $aggregatedValues = $this->compute<?= $aggregationName ?>($con);
<?php foreach ($columnPhpNames as $index => $columnName): ?>
    $this->set<?= $columnName ?>($aggregatedValues[<?= $index ?>]);
<?php endforeach; ?>
    $this->save($con);
}
