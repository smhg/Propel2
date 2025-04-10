<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Propel\Tests\Helpers\BaseTestCase;

/**
 *
 */
class CriteriaCombineFiltersTest extends BaseTestCase
{
    /**
     * @return array<array{string, Criteria, string}>>
     */
    public function CombineFiltersDataProvider(): array
    {
        Propel::getServiceContainer()->initDatabaseMaps([]);
        return [
            [
                'no combine',
                (new Criteria())
                    ->addFilter('A', 1),
                'A=1'
            ], [
                'regular AND',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->addAND('B', 2),
                'A=1 AND B=2'
            ], [
                'regular OR',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->addOr('B', 2),
                '(A=1 OR B=2)'
            ], [
                'AND with OR',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->addAnd('B', 2)
                    ->addOr('C', 3),
                'A=1 AND (B=2 OR C=3)'
            ], 
            [
                'empty combine',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->combineFilters()
                    ->endCombineFilters()
                    ->addFilter('B', 2),
                'A=1 AND B=2'
            ], 
            [
                'combine one',
                (new Criteria())
                    ->combineFilters()
                    ->addFilter('A', 1)
                    ->endCombineFilters(),
                'A=1'
            ], [
                'default combine with AND',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->combineFilters('AND')
                    ->addFilter('B', 2)
                    ->endCombineFilters(),
                'A=1 AND B=2'
            ],[
                'combine with AND',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->_or()
                    ->combineFilters('AND')
                    ->addFilter('B', 2)
                    ->endCombineFilters(),
                'A=1 AND B=2'
            ],[
                'combine with OR',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->combineFilters('OR')
                    ->addFilter('B', 2)
                    ->endCombineFilters(),
                '(A=1 OR B=2)'
            ],[
                'combine with _or()',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->_or()
                    ->combineFilters()
                    ->addFilter('B', 2)
                    ->endCombineFilters(),
                '(A=1 OR B=2)'
            ],[
                'ignores first combine andOr',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->combineFilters()
                    ->addOr('B', 2)
                    ->endCombineFilters(),
                'A=1 AND B=2'
            ], [
                'combine two',
                (new Criteria())
                    ->combineFilters()
                    ->addFilter('A', 1)
                    ->addAND('B', 2)
                    ->endCombineFilters(),
                '(A=1 AND B=2)'
            ], [
                'AND combined',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->combineFilters()
                    ->addFilter('B', 2)
                    ->addOr('C', 3)
                    ->endCombineFilters(),
                'A=1 AND (B=2 OR C=3)'
            ], [
                'missing endCombineFilters',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->combineFilters()
                    ->addFilter('B', 2)
                    ->addOr('C', 3),
                'A=1 AND ((B=2 OR C=3) ... )'
            ], [
                'combine twice with AND',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->combineFilters()
                        ->addFilter('B', 2)
                        ->addOr('C', 3)
                    ->endCombineFilters()
                    ->combineFilters()
                        ->addFilter('D', 4)
                        ->addOr('E', 5)
                    ->endCombineFilters(),
                'A=1 AND (B=2 OR C=3) AND (D=4 OR E=5)'
            ], [
                'combine twice with OR',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->combineFilters()
                        ->addFilter('B', 2)
                        ->addOr('C', 3)
                    ->endCombineFilters()
                    ->_or()
                    ->combineFilters()
                        ->addFilter('D', 4)
                        ->addAnd('E', 5)
                    ->endCombineFilters(),
                'A=1 AND ((B=2 OR C=3) OR (D=4 AND E=5))'
            ], [
                'nested combine',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->combineFilters()
                        ->addFilter('B', 2)
                        ->_or()
                        ->combineFilters()
                            ->addFilter('D', 4)
                            ->addAnd('E', 5)
                        ->endCombineFilters()
                    ->endCombineFilters(),
                'A=1 AND (B=2 OR (D=4 AND E=5))'
            ], [
                'double nested combine',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->_or()
                    ->combineFilters()
                        ->combineFilters()
                            ->addFilter('B', 2)
                            ->addOr('C', 3)
                        ->endCombineFilters()
                        ->combineFilters()
                            ->addFilter('D', 4)
                            ->addOr('E', 5)
                        ->endCombineFilters()
                    ->endCombineFilters(),
                '(A=1 OR ((B=2 OR C=3) AND (D=4 OR E=5)))'
            ], [
                'triple nested combine',
                (new Criteria())
                    ->addFilter('A', 1)
                    ->combineFilters('OR')
                        ->addFilter('B', 2)
                        ->combineFilters('OR')
                            ->addFilter('C', 3)
                            ->combineFilters('OR')
                                ->addFilter('D', 4)
                                ->addAnd('E', 5)
                            ->endCombineFilters()
                        ->endCombineFilters()
                    ->endCombineFilters(),
                '(A=1 OR (B=2 OR (C=3 OR (D=4 AND E=5))))'
            ]
        ];
    }

    /**
     * @dataProvider CombineFiltersDataProvider
     *
     * @return void
     */
    public function testCombineFilters(string $description, Criteria $c, $expectedCondition): void
    {
        $condition = $c->getFilterCollector()->__toString();
        $this->assertEquals($expectedCondition, $condition, $description);
    }
}
