
/**
 * Custom collection for <?= $modelClassName ?>.
 *
 * @extends <?= $parentType ?> 
 */
class <?= $unqualifiedClassName ?> extends <?= $parentClass ?> 
{
    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->setModel('<?= $modelClassNameFq ?>');
        $this->setFormatter(new ObjectFormatter());
    }
