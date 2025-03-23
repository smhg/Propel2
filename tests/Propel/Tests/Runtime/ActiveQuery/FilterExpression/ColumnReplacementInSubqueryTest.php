<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery;

use Propel\Tests\Bookstore\AuthorQuery;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\Bookstore\ReviewQuery;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;

/**
 */
class ColumnReplacementInSubqueryTest extends BookstoreTestBase
{
    public function setUp(): void{
        $this->markTestIncomplete();
    }
    public function ReplaceInSubqueryDataProvider(): array
    {
        return [
            [
                'description' => 'Should resolve columns from subquery',
                'inputClause' => 'saut.FirstName = ?',
                'value'  => 'asdf',
                'expectedClause' => 'saut.first_name = :p1',
            ],[
                'description' => 'Should resolve AS columns from subquery',
                'inputClause' => 'saut.MyAsColumn = ?',
                'value'  => 'asdf',
                'expectedClause' => 'saut.MyAsColumn = :p1',
            ],[
                'description' => 'Should resolve columns joined in subquery',
                'inputClause' => 'saut.SecondAuthorId = ?',
                'value'  => 'asdf',
                'expectedClause' => 'saut.second_author_id = :p1',
            ]
        ];
    }
    /**
     * @dataProvider ReplaceInSubqueryDataProvider
     *
     * @return void
     */
    public function testReplaceInSubquery(string $description, string $clause, $value, string $expectedClause)
    {
        $subquery = AuthorQuery::create('aut')
            ->joinEssayRelatedByFirstAuthorId('ess')
            ->addAsColumn('MyAsColumn', 'author.id')
        ;
        $query = BookQuery::create('bok')
            ->select('id')
            ->addSubquery($subquery, 'saut')
            ->where($clause, $value)
        ;

        $expectedSql =  'SELECT book.id AS "id" '.
                        'FROM book ' .
                        'LEFT JOIN (' .
                            'SELECT  ' .
                            'FROM author aut ' .
                            'LEFT JOIN essay ess ON (aut.id=ess.first_author_id) '.
                        ') saut'.
                        'WHERE ' . $expectedClause;

        $this->assertCriteriaTranslation($query, $expectedSql, [], $description);
    }


    /**
     * @return void
     */
    public function testReplaceInSubqueryWithJoin()
    {
        $subquery = AuthorQuery::create('aut')->joinEssayRelatedByFirstAuthorId('ess');
        $c = BookQuery::create('bok')
            ->addSubquery($subquery, 'saut')
            ->where('saut.SecondAuthorId = ?', 'asdf')
        ;

        $expectedSQL =  'SELECT  '.
                        'FROM book ' .
                        'LEFT JOIN (' .
                            'SELECT  ' .
                            'FROM author aut ' .
                            'LEFT JOIN essay ess ON (aut.id=ess.first_author_id) '.
                        ') saut'.
                        'WHERE saut.second_author_id = :p1';

        $this->assertCriteriaTranslation($c, $expectedSQL);
    }

    /**
     * @return void
     */
    protected function assertCriteriaTranslation($criteria, $expectedSql, $expectedParams = [], $message = '')
    {
        $params = [];
        $result = $criteria->createSelectSql($params);

        $this->assertEquals($this->getSql($expectedSql), $result, $message);
        $this->assertEquals($expectedParams, $params);
    }

    /**
     * @return void
     */
    public function testReplaceMainQueryColumnsInFilterSubquery()
    {
        $subquery = AuthorQuery::create('a')->where('b.AuthorId = a.FirstName');
        $c = BookQuery::create('b')->add(null, $subquery, '<');

        $expectedSql = "SELECT  FROM book WHERE < (SELECT  FROM author WHERE book.author_id = author.first_name)";

        $params = [];
        $this->assertCriteriaTranslation($c, $expectedSql, $params);
    }

    /**
     * @return void
     */
    public function testReplaceJoinQueryColumnsInFilterSubquery()
    {
        $subquery = AuthorQuery::create('a')->where('r.ReviewedBy = a.FirstName');
        $c = BookQuery::create('b')
            ->joinReview('r')
            ->add(null, $subquery, '>');

        $expectedSql = "SELECT  FROM book LEFT JOIN review r ON (book.id=r.book_id) WHERE > (SELECT  FROM author WHERE r.reviewed_by = author.first_name)";

        $params = [];
        $this->assertCriteriaTranslation($c, $expectedSql, $params, '');
    }

    /**
     * @return void
     */
    public function testReplaceUseQueryColumnsAreInFilterSubquery()
    {
        // access a column in subquery that was added through a join in a subcriteria (useQuery)
        // book <-> author <-> essay
        //  ^-> review
        $subquery = ReviewQuery::create('rev')->where('rev.ReviewedBy = ess.SecondAuthorId');
        $c = BookQuery::create('bok')
            ->useAuthorQuery('aut')
                ->joinEssayRelatedByFirstAuthorId('ess')
            ->endUse()
            ->add(null, $subquery, '<');

        $expectedSql =  'SELECT  FROM book ' .
                        'LEFT JOIN author aut ON (book.author_id=aut.id) ' .
                        'LEFT JOIN essay ess ON (aut.id=ess.first_author_id) '.
                        'WHERE < (' .
                            'SELECT  ' . 
                            'FROM review '.
                            'WHERE review.reviewed_by = ess.second_author_id'.
                        ')';

        $params = [];
        $this->assertCriteriaTranslation($c, $expectedSql, $params, '');
    }



    /**
     * @return void
     */
    public function testUseColumnNotInSubquerySelectGivesException()
    {
        $subquery = BookQuery::create('bok')
            ->select(['title']);

        $joinCondition = 'Author.Id = bok.AuthorId'; // AuthorId is not in select
        
        $authorQuery = AuthorQuery::create()
        ->addSubquery($subquery, 'sub', false)
        ->where($joinCondition);

        $this->expectException(\Exception::class);
        $params = [];
        $authorQuery->configureSelectColumns();
        $query = $authorQuery->createSelectSql($params);
echo $query;
    }

}
