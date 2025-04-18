<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Builder\Om\ObjectBuilder\AbstractCrossRelationCodeProducer;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Model\CrossRelation;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Table;
use Propel\Generator\Util\QuickBuilder;
use Propel\Tests\TestCase;

/**
 */
abstract class AbstractCrossRelationBuilderTest extends TestCase
{
    /**
     * @var string
     */
    protected $tableName = 'user';

    /**
     * @var AbstractCrossRelationCodeProducer
     */
    protected $crossFkCodeProducer;

    /**
     * @var \Propel\Generator\Model\CrossRelation
     */
    protected $crossFk;

    /**
     * Should create a table named user with a cross ref.
     *
     * @return string
     */
    abstract protected function getSchema(): string;

    /**
     * @return void
     */
    public function assertProducedCodeMatches(string $methodName, string $expectedCode)
    {
        $result = '';
        $this->callMethod($this->getCodeProducer(), $methodName, [&$result]);

        $this->assertEquals($expectedCode, $result);
    }


    protected function getCodeProducer(): AbstractCrossRelationCodeProducer
    {
        if (!$this->crossFkCodeProducer) {
            $this->init();
        }

        return $this->crossFkCodeProducer;
    }


    protected function getCrossFk(): CrossRelation
    {
        if (!$this->crossFk) {
            $this->init();
        }
        return $this->crossFk;
    }

    protected function init(): void
    {
        $objectBuilder = $this->createObjectBuilder($this->getSchema(), $this->tableName);

        $this->crossFkCodeProducer = $this->getObjectPropertyValue($objectBuilder, 'crossRelationCodeProducers')[0];
        $this->crossFk = $objectBuilder->getTable()->getCrossFks()[0];
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\ObjectBuilder
     */
    protected function createObjectBuilder(string $schema, string $tableName): ObjectBuilder
    {
        $database = QuickBuilder::parseSchema($schema);
        $table = $database->getTable($tableName);
        $objectBuilder = new ObjectBuilder($table);
        $config = new GeneratorConfig(null, static::generatorConfig);
        $objectBuilder->setGeneratorConfig($config);

        return $objectBuilder;
    }

    protected const generatorConfig = [
        'propel' => [
            'database' => [
                'connections' => [
                    'foo' => [
                        'adapter' => 'mysql',
                        'dsn' => 'mysql:foo',
                        'user' => 'foo',
                        'password' => 'foo'
                    ],
                ],
            ],
            'generator' => [
                'defaultConnection' => 'foo',
                'connections' => ['foo'],
            ],
        ]
    ];
}
