# Perpel

Perpel is a fork of the unmaintained [Propel2](https://github.com/propelorm/Propel2), an open-source Object-Relational Mapping (ORM) for PHP. It adds several improvements and fixes, including proper versioning.

[![Github actions Status](https://github.com/propelorm/Propel2/workflows/CI/badge.svg?branch=master)](https://github.com/propelorm/Propel2/actions?query=workflow%3ACI+branch%3Amaster)
[![codecov](https://codecov.io/gh/propelorm/Propel2/branch/master/graph/badge.svg?token=L1thFB9nOG)](https://codecov.io/gh/propelorm/Propel2)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%207-brightgreen.svg?style=flat)](https://phpstan.org/)
[![Code Climate](https://codeclimate.com/github/propelorm/Propel2/badges/gpa.svg)](https://codeclimate.com/github/propelorm/Propel2)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%207.4-8892BF.svg)](https://php.net/)
[![License](https://poser.pugx.org/propel/propel/license.svg)](https://packagist.org/packages/propel/propel)
[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/propelorm/Propel)


## Installation

- Replace the `require` declaration for Propel with Perpl:
```diff
  "require": {
+    "perpelorm/perpel": ">=2.0",
-    "propel/propel": "dev-main as 2.0.x-dev",
  },
```

- Remove the `vcs` entry for Propel2 dev in composer.json:
```diff
  "repositories": [
-    {
-      "type": "vcs",
-      "url": "git@github.com:propelorm/Propel2.git"
-    }
  ],
```

- Update libraries:
```bash
$ composer update
```
- Rebuild models:
```bash
$ vendor/bin/propel --config-dir <path/to/config> model:build
```
- Open a file where you call `Query::find()` and replace it with `Query::findObjects()`. If everything worked, you get return type `ObjectCollection<YourModelName>`. Yay!

## Features

Motivation for Perpel was to make features available around code-style, typing, performance and usability.

### Type-preserving queries

Improved types allow for code completion and statistic analysis.
- preserves types between calls to `useXXXQuery()`/`endUse()`
- adds methods `findObjects()`/`findTuples()`, which return typed Collection objects

```php
$q = BookQuery::create();                              // BookQuery<null>
$q = $q->useAuthorQuery();                             // AuthorQuery<BookQuery<null>>
$q = $q->useEssayRelatedByFirstAuthorIdExistsQuery();  // EssayQuery<AuthorQuery<BookQuery<null>>>
$q = $q->endUse();                                     // AuthorQuery<BookQuery<null>>
$q = $q->endUse();                                     // BookQuery<null>

$o = $q->findObjects();                                // ObjectCollection<Book>
$b = $q->getFirst();                                   // Book|null

$a = $q->findTuples();                                 // ArrayCollection
$r = $q->getFirst();                                   // array<string, mixed>|null
```

### Code cleanup and improved performance

These changes mostly improve internal readability/soundness of core Propel code. They mostly allow for easier and safe maintenance, but occasionally lead to performance improvements, for example when repetitive operations on strings are replaced by proper data structures.

Some notable changes:
- columns in queries are turned to objects, which leads to more readable code and makes tons of string operations obsolete (~30-50% faster query build time, see [#24](https://github.com/mringler/perpel/pull/24))
- fixes some confusing names (Criteria vs Criterion)
-  spreads out some "one size fits none" overloads, i.e. `Criteria::map` becomes `Criteria::columnFilters` and `Criteria::updateValues`

### Nested filters through operators

Introduces `Criteria::combineFilters()`/`Criteria::endCombineFilters()` which build nested filter conditions:
```php
// A=1 AND (B=2 OR C=3) AND (D=4 OR E=5)
(new Criteria())
  ->addFilter('A', 1)
  ->combineFilters()
    ->addFilter('B', 2)
    ->addOr('C', 3)
  ->endCombineFilters()
  ->combineFilters()
    ->addFilter('D', 4)
    ->addOr('E', 5)
  ->endCombineFilters()
```
Previously, this required to register the individual parts under an arbitrary name using `Criteria::addCond()` and then combining them with `Criteria::combine()`, possibly under another arbitrary name for further processing.

## Breaking Changes

Perpel is fully backwards compatible with Propel2, with few exceptions. They mostly affect the low-level Criteria interface. Impact for regular users should be slim to none.

### Set update value and add filter use dedicated methods

*Affects manual use of Criteria::update() -  does not affect updates through Propel*

Propel uses the same methods to set update values and filters. As a consequence, it requires a second Criteria to distinguish between filters and values, and a peculiar format to register update expressions:
```php
$bookQuery = BookQuery::create()->add('id', 42);
$value = new Criteria
  ->add('title', 'foo')
  ->add('count', ['raw' => 'count + ?', 'value' => 5'], Criteria::CUSTOM_EQUAL)`);
$bookQuery->update($value);
```
Now dedicated methods are used to add filters or set update values/expressions, the update can be performed on a single query:
```php
$bookQuery = BookQuery::create()
  ->addFilter('id', 42)
  ->setUpdateValue('title', 'foo')
  ->setUpdateExpression('count', 'count + ?', 5)
  ->update();
```
The `add()` method can still be used to set filter, but is deprecated in favor of `addFilter()`. Update values cannot be added using `add()`.

### UPDATE only affects one table per criteria
*Affects manual use of Criteria::update() -  does not affect updates through Propel*

Propel allows to update multiple tables in the same query:

```php
$criteria = (new Criteria())->add('a.name', 'foo')->add('b.count', 10, '>');
$values
  ->add('a.comment', 'I am foo')
  ->add('b.comment', 'I have count over 10')
  ->add('c.title', 'No title but me');
$criteria->update($values);
// runs three queries:
// UPDATE a SET comment="I am foo" WHERE name="foo";
// UPDATE b SET comment="I have count over 10" WHERE count > 10;
// UPDATE c SET title="No title but me";
```
This requires table names in filters to match table names in values, which is not save, considering that Propel allows multiple names and aliases but often fails to resolve them.

Now `update()` produces a single query and (in above example) subsequently a database error, when the columns cannot be found on the table. Usage has to be replaced with individual objects.


### DELETE only affects one table per criteria
*Affects manual use of Criteria::delete() or TableMap::doDelete()-  does not affect updates through Propel*

Same as with `Criteria::update()` above, delete can only affect one table per object.
```php
// Causes exception now
$c = new Criteria();
$c->add(BookTableMap::COL_ID, $hp->getId());
$c->add(AuthorTableMap::COL_ID, $hp->getAuthorId());
$c->add(PublisherTableMap::COL_ID, $hp->getPublisherId());
BookTableMap::doDelete($c);
```
Use model objects (`$hp->delete()`) or query (`BookQuery::create()->filterById($hp->getId())->delete()`) to delete, one for each table.

### Forcing table alias in UPDATE on Sqlite
*Affects users that force table alias on a DBMS that does not allow table aliases in UPDATE*

Even when forcing an alias in an update, Propel will not use it.
```php
BookQuery::create()->setModelAlias('b', true)->...->update();
```
Now it does, breaking DBMSs that do not allow aliases in UPDATE (like Sqlite).

This will be resolved at some point, but for now: if you don't need it, don't force it.

### Deprecated low-level API methods
*Affects usage of old API methods like `Criteria::get()`, `Criteria::put()`, etc.*

Obscure names, replaced functionality, unclear use-case - removing those methods is part of de-cluttering the query object interface and code. Internally, they are not used anymore.

The methods are still available, but only through magic `__call()`. They do not appear on the query object interface and thus are not suggested by autocomplete. Using them will trigger a deprecation warning. The method docs in `src/Propel/Runtime/ActiveQuery/DeprecatedCriteriaMethods.php` describe how to replace them.

A full list of deprecated methods can be found in [#28](https://github.com/mringler/perpel/pull/28). The only notable mention is `Criteria::addCond()` and `Criteria::combine()`, which are replaced by `Criteria::combineFilters()` (see above).

## Outlook

Some things I would like to do when I find the time:
- Delay resolving of column names until query is created.
- Automatically build subclasses of ObjectCollection for each model class, which provide typed entries to `ObjectCollection::populateRelation()` for model relations (i.e. `AuthorQuery::create()->findObjects()->populateBooks()`).
- Get prepared statement parameters without building filters for QueryCache behavior.

## Disclaimer

Built with care, tested, but not yet proven over time. Provided as is. Test before deployment. Let me know how it goes!

Feedback and PRs are welcome.

## License

MIT. See the `LICENSE` file for details.
