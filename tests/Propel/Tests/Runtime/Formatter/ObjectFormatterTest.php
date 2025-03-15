<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\Formatter;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Formatter\ObjectFormatter;
use Propel\Runtime\Propel;
use Propel\Tests\Bookstore\AuthorCollection;
use Propel\Tests\Bookstore\Book;
use Propel\Tests\Bookstore\Map\BookTableMap;
use Propel\Tests\Helpers\Bookstore\BookstoreDataPopulator;
use Propel\Tests\Helpers\Bookstore\BookstoreEmptyTestBase;

/**
 * Test class for ObjectFormatter.
 *
 * @author Francois Zaninotto
 *
 * @group database
 */
class ObjectFormatterTest extends BookstoreEmptyTestBase
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
        $formatter = new ObjectFormatter();
        $this->expectException(PropelException::class, 'should throw exception when called with no valid criteria');
        try {
            $dataFetcher = $this->con->query('SELECT * FROM book');
            $formatter->format($dataFetcher);
        } finally {
            $dataFetcher->close();
        }
    }

    /**
     * @return void
     */
    public function testFormatValidClass()
    {
        $stmt = $this->con->query('SELECT * FROM book');
        $formatter = new ObjectFormatter();
        $formatter->setClass('\Propel\Tests\Bookstore\Book');
        $books = $formatter->format($stmt);
        $this->assertTrue($books instanceof ObjectCollection);
        $this->assertEquals(4, $books->count());
    }

    /**
     * @return void
     */
    public function testFormatValidClassCustomCollection()
    {
        $stmt = $this->con->query('SELECT * FROM author');
        $formatter = new ObjectFormatter();
        $formatter->setClass('\Propel\Tests\Bookstore\Author');
        $authors = $formatter->format($stmt);
        $this->assertTrue($authors instanceof AuthorCollection);
    }

    /**
     * @return void
     */
    public function testFormatManyResults()
    {
        $stmt = $this->con->query('SELECT * FROM book');
        $formatter = new ObjectFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));
        $books = $formatter->format($stmt);

        $this->assertTrue($books instanceof Collection, 'ObjectFormatter::format() returns a PropelCollection');
        $this->assertEquals(4, count($books), 'ObjectFormatter::format() returns as many rows as the results in the query');
        foreach ($books as $book) {
            $this->assertTrue($book instanceof Book, 'ObjectFormatter::format() returns an array of Model objects');
        }
    }

    /**
     * @return void
     */
    public function testFormatOneResult()
    {
        $stmt = $this->con->query("SELECT id, title, isbn, price, publisher_id, author_id FROM book WHERE book.TITLE = 'Quicksilver'");
        $formatter = new ObjectFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));
        $books = $formatter->format($stmt);

        $this->assertTrue($books instanceof Collection, 'ObjectFormatter::format() returns a PropelCollection');
        $this->assertEquals(1, count($books), 'ObjectFormatter::format() returns as many rows as the results in the query');
        $book = $books->shift();
        $this->assertTrue($book instanceof Book, 'ObjectFormatter::format() returns an array of Model objects');
        $this->assertEquals('Quicksilver', $book->getTitle(), 'ObjectFormatter::format() returns the model objects matching the query');
    }

    /**
     * @return void
     */
    public function testFormatNoResult()
    {
        $stmt = $this->con->query("SELECT * FROM book WHERE book.TITLE = 'foo'");
        $formatter = new ObjectFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));
        $books = $formatter->format($stmt);

        $this->assertTrue($books instanceof Collection, 'ObjectFormatter::format() returns a PropelCollection');
        $this->assertEquals(0, count($books), 'ObjectFormatter::format() returns as many rows as the results in the query');
    }

    /**
     * @return void
     */
    public function testFormatOneNoCriteria()
    {
        $formatter = new ObjectFormatter();
        $this->expectException(PropelException::class, 'should throw exception when called with no valid criteria');
        try {
            $stmt = $this->con->query('SELECT * FROM book');
            $formatter->formatOne($stmt);
        } finally {
            $stmt->close();
        }
    }

    /**
     * @return void
     */
    public function testFormatOneManyResults()
    {
        $stmt = $this->con->query('SELECT * FROM book');
        $formatter = new ObjectFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));
        $book = $formatter->formatOne($stmt);

        $this->assertTrue($book instanceof Book, 'ObjectFormatter::formatOne() returns a model object');
    }

    /**
     * @return void
     */
    public function testFormatOneNoResult()
    {
        $stmt = $this->con->query("SELECT * FROM book WHERE book.TITLE = 'foo'");
        $formatter = new ObjectFormatter();
        $formatter->init(new ModelCriteria('bookstore', '\Propel\Tests\Bookstore\Book'));
        $book = $formatter->formatOne($stmt);

        $this->assertNull($book, 'ObjectFormatter::formatOne() returns null when no result');
    }
}
