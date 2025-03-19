<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery;

use Propel\Tests\Bookstore\AuthorQuery;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\Bookstore\EssayQuery;
use Propel\Tests\Bookstore\ReviewQuery;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;

/**
 * @group database
 */
class ColumnReplacementInSubqueryTest extends BookstoreTestBase
{
    /**
     * @return void
     */
    protected function assertCriteriaTranslation($criteria, $expectedSql, $expectedParams, $message = '')
    {
        $params = [];
        $result = $criteria->createSelectSql($params);

        $this->assertEquals($expectedSql, $result, $message);
        $this->assertEquals($expectedParams, $params);
    }

    /**
     * @return void
     */
    public function testReplaceMainQueryColumns()
    {
        $subquery = AuthorQuery::create('a')->where('b.AuthorId = a.FirstName');
        $c = BookQuery::create('b')->add(null, $subquery, '<');

        $expectedQuery = "SELECT  FROM book WHERE < (SELECT  FROM author WHERE book.author_id = author.first_name)";
        $sql = $this->getSql($expectedQuery);

        $params = [];
        $this->assertCriteriaTranslation($c, $sql, $params);
    }

    /**
     * @return void
     */
    public function testReplaceJoinQueryColumns()
    {
        $subquery = AuthorQuery::create('a')->where('r.ReviewedBy = a.FirstName');
        $c = BookQuery::create('b')
            ->joinReview('r')
            ->add(null, $subquery, '>');

        $expectedQuery = "SELECT  FROM book LEFT JOIN review r ON (book.id=r.book_id) WHERE > (SELECT  FROM author WHERE r.reviewed_by = author.first_name)";
        $sql = $this->getSql($expectedQuery);

        $params = [];
        $this->assertCriteriaTranslation($c, $sql, $params, '');
    }

    /**
     * @return void
     */
    public function testReplaceUseQueryColumnsAre()
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

        $expectedQuery =    'SELECT  FROM book ' .
                            'LEFT JOIN author aut ON (book.author_id=aut.id) ' .
                            'LEFT JOIN essay ess ON (aut.id=ess.first_author_id) '.
                            'WHERE < (' .
                                'SELECT  ' . 
                                'FROM review '.
                                'WHERE review.reviewed_by = ess.second_author_id'.
                            ')';
        $sql = $this->getSql($expectedQuery);

        $params = [];
        $this->assertCriteriaTranslation($c, $sql, $params, '');
    }
}
