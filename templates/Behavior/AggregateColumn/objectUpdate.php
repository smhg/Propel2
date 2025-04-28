
/**
 * Updates the aggregate column <?=$column->getName()?>
 *
 * @param \Propel\Runtime\Connection\ConnectionInterface $con A connection object
 */
public function update<?=$column->getPhpName()?>(ConnectionInterface $con)
{
    $this->set<?=$column->getPhpName()?>($this->compute<?=$column->getPhpName()?>($con));
    $this->save($con);
}
