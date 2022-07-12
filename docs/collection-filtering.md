## Collection Filtering

Collection is filtered through its `findBy()` method; alternatively, `getBy()` and `getByChecked()` shortcuts accept the same filtering expressions.

The simplest filtering is an array of conditions. These conditions are passed as the only parameter of the `findBy()` method. The associative array consists of entity property names and their wanted values. Keys can contain an optional operator. The default operator is equality. Let's see the example:

```php
$books = $orm->books->findBy([
	'author' => $author->id,
	'publishedAt<=' => new DateTimeImmutable(),
]);
```

Allowed operators are `=`, `!=`, `<=`, `<`, `>=`, `>`, and `~` (like -- see later), append it directly after the property name (without any space or other white-char).

You can filter the collection by conditions with condition filtering by a relationships traversing; use a *traversing expression*: it consists of the path delimited by `->`, i.e. the same arrow you use in PHP.

```php
// find all books which were authored by Jon Snow
$orm->books->findBy(['author->name' => 'Jon Snow']);

// find all books which were not translated by Jon Snow
$orm->books->findBy(['translator->name!=' => 'Jon Snow']);
```

The described syntax may be expanded to support a `OR` logical conjunction. Prepend the `ICollection::OR` operator as a first value of the filtering array:

```php
// finds all books which were authored or translated by one specific person
$books = $orm->books->findBy([
	ICollection::OR,
	'author->name' => 'Jon Snow',
	'translator->name' => 'Jon Snow',
]);
```

This relationship filtering is designed mainly for has-one relationship. Has-many relationships usually utilize an aggregation function, which is covered later in this chapter. Still, this filtering syntax works for has-many relationships. Such expression will select all entries where at least one of the entities in the has-many relationship meets the conditions.

You may nest the filtering structure; use the same syntax repeatedly:

```php
// find all man older than 10 years and woman younger than 12 years
$authors = $orm->author->findBy([
	ICollection::OR,
	[
		ICollection::AND,
		'age>=' => 10,
		'sex' => 'male',
	],
	[
		ICollection::AND,
		'age<=' => 12,
		'sex' => 'female',
	],
]);
```

The previous example can be shortened because the `AND` operator is the default logical operator.

```php
// find all man older than 10 years and woman younger than 12 years
$authors = $orm->author->findBy([
	ICollection::OR,
	[
		'age>=' => 10,
		'gender' => 'male',
	],
	[
		'age<=' => 12,
		'gender' => 'female',
	],
]);
```

<div class="note">

Filtering over virtual properties is generally unsupported and provides undefined behavior.
</div>

#### LIKE filtering

`LIKE` filtering is supported and directly provided in Nextras Orm. Use `~` compare operator. The value has to be wrapped as `Nextras\Orm\Collection\Expression\LikeExpression` instance, use its static builders to create one: choose from `startsWith`, `endsWith` or `contains`. Alternatively, you may provide your wildcard expression with the `raw` method. Be aware, that the `raw` method expects sanitized input.

```php
// finds all users with email hosted on gmail.com
$users->findBy([
    'emails~' => LikeExpression::endsWith('@gmail.com'),
]);
```

#### Aggregation

Aggregation functions can be used for both collection filtering and sorting. They are based on [collection functions | collection-functions] -- a general approach for custom collection modification.

Orm brings these prepared aggregation functions:

- CountAggregateFunction
- SumAggregateFunction
- AvgAggregateFunction
- MinAggregateFunction
- MaxAggregateFunction

All those functions are implemented both for Dbal and Array collections, and they are registered in a repository as commonly provided collection functions.

To use a collection function, pass the function name and then its arguments –- all aggregation functions take only one argument – an expression that should be aggregated. Let’s see an example:

```php
use Nextras\Orm\Collection\Functions\CountAggregateFunction;

$authorsCollection->orderBy(
    [CountAggregateFunction::class, 'books->id']
);
```

In the example we sort the collection of authors by the count of their books, i.e. authors with the least books will be at the beginning. The example allows the same "property expression" you use for filtering. You can reverse the ordering:

```php
use Nextras\Orm\Collection\Functions\CountAggregateFunction;
use Nextras\Orm\Collection\ICollection;

$authorsCollection->orderBy(
    [CountAggregateFunction::class, 'books->id'],
    ICollection::DESC
);
```

Filtering by an aggregation requires a little more. Let's filter the collection by authors who have written more than 2 books. Using `CountAggregationFunction` itself won’t be enough. You need to compare its result with the wanted number, `2` this time. To do so, use built-in `Compare*Function`. Choose function depending on the wanted operator. The function takes a property expression on the left, and a value to compare (on the right).

```php
use Nextras\Orm\Collection\Functions\CompareGreaterThanFunction;
use Nextras\Orm\Collection\Functions\CountAggregateFunction;

// SELECT * FROM authors
//    LEFT JOIN books ON (...)
// GROUP BY authors.id
// HAVING COUNT(books.id) > 2
$authorsCollection->findBy(
    [
        CompareGreaterThanFunction::class,
        [CountAggregateFunction::class, 'books->id'],
        2,
    ]
);
```

You can nest these function calls together. This approach is very powerful and flexible, though, sometimes quite verbose. To ease this issue you may create your own wrappers (not included in Orm!).

```php
class Aggregate {
    public static function count(string $expression): array {
        return [CountAggregateFunction::class, $expression];
    }
}
class Compare {
    public static function gt(string $expression, $value): array {
        return [
            CompareGreaterThanFunction::class,
            $expression,
            $value,
        ];
    }
}

// filters authors who have more than 2 books
// and sorts them by the count of their books descending
$authorsCollection
    ->findBy(Compare::gt(Aggregate::count('books->id'), 2))
    ->orderBy(Aggregate::count('books->id'), ICollection::DESC);
```

Feel free to [share feedback](https://github.com/nextras/orm/discussions/categories/show-and-tell) about using aggregation functions.
