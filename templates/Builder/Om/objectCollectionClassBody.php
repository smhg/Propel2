<?php
/**
 * @var array<array{relationIdentifier: string, relationIdentifierInMethod: string, collectionClassType: string, collectionClassName: string}> $relationMapping
 */
foreach ($relationMapping as $mapping): 
?>

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return <?= $mapping['collectionClassType'] ?> 
     */
    public function populate<?= $mapping['relationIdentifierInMethod'] ?>(?Criteria $criteria = null, ?ConnectionInterface $con = null): <?= $mapping['collectionClassName'] ?> 
    {
        /** @var <?= $mapping['collectionClassType'] ?> $collection */
        $collection = $this->populateRelation('<?= $mapping['relationIdentifier'] ?>', $criteria, $con);

        return $collection;
    }
<?php endforeach ?>
