<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\Bookstore\RecordLabelQuery;
use Propel\Tests\TestCaseFixturesDatabase;

/**
 * 
 */
class ModelCriteriaPkFilterDetectionTest extends TestCaseFixturesDatabase
{
    public function UpdateAffectsSingleRowDataProvider(): array
    {
        static::checkInit();

        return [
            ['empty query', BookQuery::create(), false],
            ['pk not covered', BookQuery::create()->filterByTitle(), false],
            ['single pk covered by filterBy', BookQuery::create()->filterById(), true],
            ['single pk covered and other', BookQuery::create()->filterById()->filterByTitle(), false],
            ['single pk covered by where', BookQuery::create()->where('book.id = ?', 42), true],
            ['single pk covered by condition', BookQuery::create('b')->addFilter('book.id', 42, '='), true],
            ['single pk covered by where with alias', BookQuery::create('b')->where('b.id = ?', 42), true],
            ['single pk covered by where with wrong alias', BookQuery::create('b')->where('book.id = ?', 42), true],
            ['single pk covered by condition with alias', BookQuery::create('b')->addFilter('b.id', 42, '='), true],

            // currently not implemented
            // ['single pk in IN', BookQuery::create('b')->useInBookSummaryQuery()->endUse(), false],
            // ['single pk less equal', BookQuery::create()->filterById(42, '<='), false],
            // ['single pk with OR', BookQuery::create()->filterById(42)->_or()->filterById(43), false],

            ['complex pk partly covered 1', RecordLabelQuery::create()->filterById(), false],
            ['complex pk partly covered 2', RecordLabelQuery::create()->filterByAbbr(), false],
            ['complex pk partly covered and other', RecordLabelQuery::create()->filterById()->filterByName(), false],
            ['complex pk fully covered', RecordLabelQuery::create()->filterById()->filterByAbbr(), true],
            ['complex pk in WHERE', RecordLabelQuery::create('r')->where('r.id = ?', 42)->where('r.abbr = ?', ''), true],

            // currently not implemented
            // ['complex pk in complex WHERE', RecordLabelQuery::create('r')->where('r.id = ? AND r.abbr = ?', [42, '']), true],
        ];
    }

    /**
     * @dataProvider UpdateAffectsSingleRowDataProvider
     *
     * @return void
     */
    public function testUpdateAffectsSingleRow(string $description, ModelCriteria $query, bool $expected): void
    {
        $actual = $this->callMethod($query, 'updateAffectsSingleRow');
        $this->assertSame($expected, $actual, $description);
    }
    
}
