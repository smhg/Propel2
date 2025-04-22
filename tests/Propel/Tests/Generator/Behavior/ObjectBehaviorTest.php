<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Behavior;

use Propel\Tests\Bookstore\Behavior\Base\testObjectFilter;
use Propel\Tests\Bookstore\Behavior\Table3;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;

/**
 * Tests the generated Object behavior hooks.
 *
 * @author Francois Zaninotto
 *
 * @group database
 */
class ObjectBehaviorTest extends BookstoreTestBase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        //prevent issue DSN not Found
        self::$isInitialized = false;
        parent::setUp();
    }

    /**
     * @return void
     */
    public function testObjectAttributes()
    {
        $t = new Table3();
        $this->assertEquals($t->customAttribute, 1, 'objectAttributes hook is called when adding attributes');
    }

    /**
     * @return void
     */
    public function testPreSave()
    {
        $t = new Table3();
        $t->flagDump['preSave'] = 0;
        $t->save();
        $this->assertEquals($t->flagDump['preSave'], 1, 'preSave hook is called on object insertion');
        $this->assertEquals('Propel\Generator\Builder\Om\ObjectBuilder', $t->flagDump['preSaveBuilder'], 'preSave hook is called with the object builder as parameter');
        $this->assertFalse($t->flagDump['preSaveIsAfterSave'], 'preSave hook is called before save');
        $t->flagDump['preSave'] = 0;
        $t->setTitle('foo');
        $t->save();
        $this->assertEquals($t->flagDump['preSave'], 1, 'preSave hook is called on object modification');
    }

    /**
     * @return void
     */
    public function testPostSave()
    {
        $t = new Table3();
        $t->flagDump['postSave'] = 0;
        $t->save();
        $this->assertEquals($t->flagDump['postSave'], 1, 'postSave hook is called on object insertion');
        $this->assertEquals('Propel\Generator\Builder\Om\ObjectBuilder', $t->flagDump['postSaveBuilder'], 'postSave hook is called with the object builder as parameter');
        $this->assertTrue($t->flagDump['postSaveIsAfterSave'], 'postSave hook is called after save');
        $t->flagDump['postSave'] = 0;
        $t->setTitle('foo');
        $t->save();
        $this->assertEquals($t->flagDump['postSave'], 1, 'postSave hook is called on object modification');
    }

    /**
     * @return void
     */
    public function testObjectBuilderPreInsert()
    {
        $t = new Table3();
        $t->flagDump['preInsert'] = 0;
        $t->save();
        $this->assertEquals($t->flagDump['preInsert'], 1, 'preInsert hook is called on object insertion');
        $this->assertEquals('Propel\Generator\Builder\Om\ObjectBuilder', $t->flagDump['preInsertBuilder'], 'preInsert hook is called with the object builder as parameter');
        $this->assertFalse($t->flagDump['preInsertIsAfterSave'], 'preInsert hook is called before save');
        $t->flagDump['preInsert'] = 0;
        $t->setTitle('foo');
        $t->save();
        $this->assertEquals($t->flagDump['preInsert'], 0, 'preInsert hook is not called on object modification');
    }

    /**
     * @return void
     */
    public function testPostInsert()
    {
        $t = new Table3();
        $t->flagDump['postInsert'] = 0;
        $t->save();
        $this->assertEquals($t->flagDump['postInsert'], 1, 'postInsert hook is called on object insertion');
        $this->assertEquals('Propel\Generator\Builder\Om\ObjectBuilder', $t->flagDump['postInsertBuilder'], 'postInsert hook is called with the object builder as parameter');
        $this->assertTrue($t->flagDump['postInsertIsAfterSave'], 'postInsert hook is called after save');
        $t->flagDump['postInsert'] = 0;
        $t->setTitle('foo');
        $t->save();
        $this->assertEquals($t->flagDump['postInsert'], 0, 'postInsert hook is not called on object modification');
    }

    /**
     * @return void
     */
    public function testPreUpdate()
    {
        $t = new Table3();
        $t->flagDump['preUpdate'] = 0;
        $t->save();
        $this->assertEquals($t->flagDump['preUpdate'], 0, 'preUpdate hook is not called on object insertion');
        $t->flagDump['preUpdate'] = 0;
        $t->setTitle('foo');
        $t->save();
        $this->assertEquals($t->flagDump['preUpdate'], 1, 'preUpdate hook is called on object modification');
        $this->assertEquals('Propel\Generator\Builder\Om\ObjectBuilder', $t->flagDump['preUpdateBuilder'], 'preUpdate hook is called with the object builder as parameter');
        $this->assertFalse($t->flagDump['preUpdateIsAfterSave'], 'preUpdate hook is called before save');
    }

    /**
     * @return void
     */
    public function testPostUpdate()
    {
        $t = new Table3();
        $t->flagDump['postUpdate'] = 0;
        $t->save();
        $this->assertEquals($t->flagDump['postUpdate'], 0, 'postUpdate hook is not called on object insertion');
        $t->flagDump['postUpdate'] = 0;
        $t->setTitle('foo');
        $t->save();
        $this->assertEquals($t->flagDump['postUpdate'], 1, 'postUpdate hook is called on object modification');
        $this->assertEquals('Propel\Generator\Builder\Om\ObjectBuilder', $t->flagDump['postUpdateBuilder'], 'postUpdate hook is called with the object builder as parameter');
        $this->assertTrue($t->flagDump['postUpdateIsAfterSave'], 'postUpdate hook is called after save');
    }

    /**
     * @return void
     */
    public function testPreDelete()
    {
        $t = new Table3();
        $t->save();
        $t->flagDump['preDelete'] = 0;
        $t->delete();
        $this->assertEquals($t->flagDump['preDelete'], 1, 'preDelete hook is called on object deletion');
        $this->assertEquals('Propel\Generator\Builder\Om\ObjectBuilder', $t->flagDump['preDeleteBuilder'], 'preDelete hook is called with the object builder as parameter');
        $this->assertTrue($t->flagDump['preDeleteIsBeforeDelete'], 'preDelete hook is called before deletion');
    }

    /**
     * @return void
     */
    public function testPostDelete()
    {
        $t = new Table3();
        $t->save();
        $t->flagDump['postDelete'] = 0;
        $t->delete();
        $this->assertEquals($t->flagDump['postDelete'], 1, 'postDelete hook is called on object deletion');
        $this->assertEquals('Propel\Generator\Builder\Om\ObjectBuilder', $t->flagDump['postDeleteBuilder'], 'postDelete hook is called with the object builder as parameter');
        $this->assertFalse($t->flagDump['postDeleteIsBeforeDelete'], 'postDelete hook is called before deletion');
    }

    /**
     * @return void
     */
    public function testObjectMethods()
    {
        $t = new Table3();
        $this->assertTrue(method_exists($t, 'hello'), 'objectMethods hook is called when adding methods');
        $this->assertEquals('Propel\Generator\Builder\Om\ObjectBuilder', $t->hello(), 'objectMethods hook is called with the object builder as parameter');
    }

    /**
     * @return void
     */
    public function testObjectCall()
    {
        $t = new Table3();
        $this->assertEquals('bar', $t->foo(), 'objectCall hook is called when building the magic __call()');
    }

    /**
     * @return void
     */
    public function testObjectFilter()
    {
        $t = new Table3();
        $this->assertTrue(
            class_exists('Propel\Tests\Bookstore\Behavior\Base\testObjectFilter'),
            'objectFilter hook allows complete manipulation of the generated script'
        );
        $this->assertEquals(
            'Propel\Generator\Builder\Om\ObjectBuilder',
            testObjectFilter::FOO,
            'objectFilter hook is called with the object builder as parameter'
        );
    }
}
