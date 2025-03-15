<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\Formatter;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Formatter\ArrayFormatter;
use Propel\Tests\Helpers\Bookstore\BookstoreDataPopulator;
use Propel\Tests\Helpers\Bookstore\BookstoreEmptyTestBase;

/**
 * Test class for ArrayFormatter.
 *
 * @author Francois Zaninotto
 *
 * @group database
 */
class ArrayFormatterTest extends BookstoreEmptyTestBase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        BookstoreDataPopulator::populate();
    }

    /**
     * @return void
     */
    public function testFormatNoCriteria()
    {
        $formatter = new ArrayFormatter();
        $this->expectException(PropelException::class, 'should throw exception when called with no valid criteria');
        try{
            $dataFetcher = $this->con->query('SELECT * FROM book');
            $formatter->format($dataFetcher);
        } finally {
            $dataFetcher->close();
        }
    }

    /**
     * @return void
     */
    public function testFormatManyResults()
    {
        $dataFetcher = $this->con->query('SELECT * FROM book');
        $formatter = new ArrayFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));
        $books = $formatter->format($dataFetcher);
        $this->assertInstanceOf(Collection::class, $books, 'ArrayFormatter::format() returns a PropelCollection');
        $this->assertCount(4, $books, 'ArrayFormatter::format() returns as many rows as the results in the query');
        foreach ($books as $book) {
            $this->assertIsArray($book, 'ArrayFormatter::format() returns an array of arrays');
        }
    }

    /**
     * @return void
     */
    public function testFormatOneResult()
    {
        $dataFetcher = $this->con->query("SELECT id, title, isbn, price, publisher_id, author_id FROM book WHERE book.TITLE = 'Quicksilver'");
        $formatter = new ArrayFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));
        $books = $formatter->format($dataFetcher);

        $this->assertInstanceOf(Collection::class, $books, 'ArrayFormatter::format() returns a PropelCollection');
        $this->assertCount(1, $books, 'ArrayFormatter::format() returns as many rows as the results in the query');
        $book = $books->shift();
        $this->assertIsArray($book, 'ArrayFormatter::format() returns an array of arrays');
        $this->assertEquals('Quicksilver', $book['Title'], 'ArrayFormatter::format() returns the arrays matching the query');
        $expected = ['Id', 'Title', 'ISBN', 'Price', 'PublisherId', 'AuthorId'];
        $this->assertEquals($expected, array_keys($book), 'ArrayFormatter::format() returns an associative array with column phpNames as keys');
    }

    /**
     * @return void
     */
    public function testFormatNoResult()
    {
        $dataFetcher = $this->con->query("SELECT * FROM book WHERE book.TITLE = 'foo'");
        $formatter = new ArrayFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));
        $books = $formatter->format($dataFetcher);

        $this->assertInstanceOf(Collection::class, $books, 'ArrayFormatter::format() returns a PropelCollection');
        $this->assertCount(0, $books, 'ArrayFormatter::format() returns as many rows as the results in the query');
    }

    /**
     * @return void
     */
    public function testFormatOneNoCriteria()
    {
        $formatter = new ArrayFormatter();
        $this->expectException(PropelException::class, 'should throw exception when called with no valid criteria');
        try {
            $dataFetcher = $this->con->query('SELECT * FROM book');
            $formatter->formatOne($dataFetcher);
        } finally {
            $dataFetcher->close();
        }
    }

    /**
     * @return void
     */
    public function testFormatOneManyResults()
    {
        $dataFetcher = $this->con->query('SELECT * FROM book');
        $formatter = new ArrayFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));
        $book = $formatter->formatOne($dataFetcher);

        $this->assertIsArray($book, 'ArrayFormatter::formatOne() returns an array');
        $this->assertEquals(['Id', 'Title', 'ISBN', 'Price', 'PublisherId', 'AuthorId'], array_keys($book), 'ArrayFormatter::formatOne() returns a single row even if the query has many results');
    }

    /**
     * @return void
     */
    public function testFormatOneNoResult()
    {
        $dataFetcher = $this->con->query("SELECT * FROM book WHERE book.TITLE = 'foo'");
        $formatter = new ArrayFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));
        $book = $formatter->formatOne($dataFetcher);

        $this->assertNull($book, 'ArrayFormatter::formatOne() returns null when no result');
    }
}
