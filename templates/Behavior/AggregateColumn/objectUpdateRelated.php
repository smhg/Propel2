
/**
 * Update the aggregate column in the related <?=$relationName?> object
 *
 * @param \Propel\Runtime\Connection\ConnectionInterface $con A connection object
 */
protected function updateRelated<?=$relationName.$aggregateName?>(ConnectionInterface $con)
{
    if ($<?=$variableName?> = $this->get<?=$relationName?>()) {
        $<?=$variableName?>-><?=$updateMethodName?>($con);
    }
    if ($this->old<?=$relationName.$aggregateName?>) {
        $this->old<?=$relationName.$aggregateName?>-><?=$updateMethodName?>($con);
        $this->old<?=$relationName.$aggregateName?> = null;
    }
}
