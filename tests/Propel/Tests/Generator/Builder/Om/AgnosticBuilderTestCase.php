<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Util\QuickBuilder;
use Propel\Tests\TestCase;

/**
 * Utility class for QueryBuilder.
 */
class AgnosticBuilderTestCase extends TestCase
{
    
    /**
     * @template BuilderClass of \Propel\Generator\Builder\Om\AbstractOMBuilder
     * @param class-string<BuilderClass> $builderClass
     * @param string $schema
     * @param string $tableName
     *
     * @return BuilderClass
     */
    protected function setupBuilder(string $builderClass, string $schema, string $tableName): AbstractOMBuilder
    {
        $database = QuickBuilder::parseSchema($schema);
        $table = $database->getTable($tableName);
        $builder = new $builderClass($table);
        $config = new GeneratorConfig(null, static::generatorConfig);
        $builder->setGeneratorConfig($config);

        return $builder;
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
