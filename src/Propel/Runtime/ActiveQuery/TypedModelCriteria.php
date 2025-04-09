<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery;

/**
 * Adds a generic type to ModelCriteria for type-safe use of endUse(). Used as
 * parent class for Query classes.
 *
 * This class is not meant to implement methods.
 *
 * @template ParentQuery of \Propel\Runtime\ActiveQuery\ModelCriteria|null = null
 *
 * @method ParentQuery endUse()
 */
class TypedModelCriteria extends ModelCriteria
{
}
