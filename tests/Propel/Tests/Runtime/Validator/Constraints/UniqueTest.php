<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\Validator\Constraints;

use Propel\Runtime\Validator\Constraints\Unique;
use Propel\Tests\TestCase;

class UniqueTest extends TestCase
{
    /**
     * @return void
     */
    public function testNamedArguments()
    {
        $constraint = new Unique(message: 'taken', column: 'title', groups: ['foo']);

        $this->assertSame('taken', $constraint->message);
        $this->assertSame('title', $constraint->column);
        $this->assertSame(['foo'], $constraint->groups);
    }

    /**
     * @return void
     */
    public function testOptionsArray()
    {
        $constraint = new Unique(['message' => 'taken', 'column' => 'title', 'groups' => ['foo']]);

        $this->assertSame('taken', $constraint->message);
        $this->assertSame('title', $constraint->column);
        $this->assertSame(['foo'], $constraint->groups);
    }

    /**
     * @return void
     */
    public function testDefaults()
    {
        $constraint = new Unique();

        $this->assertSame('This value is already stored in your database', $constraint->message);
        $this->assertSame('', $constraint->column);
        $this->assertSame(['Default'], $constraint->groups);
    }
}
