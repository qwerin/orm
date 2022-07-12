## Collection Functions

Collection functions are a powerful extension point that will allow you to write a custom filtering or an ordering behavior.

The custom filtering or ordering requires its own implementation for each storage implementation; ideally you write it for both Dbal & Array to allow use of persisted and un-persisted collections. Both of the Dbal & Array implementations bring their own interface you have to implement directly.

<div class="note">

**Why do we have ArrayCollection and DbalCollection?**

Collection itself is independent of storage implementation. It is your choice if your collection function will work in both cases - for `ArrayCollection` and `DbalCollection`. Let us remind you, `ArrayCollection`s are commonly used in relationships when you set new entities into the relationship but until the relationship is persisted, you will work with an `ArrayCollection`.
</div>

Collection functions can be used in `ICollection::findBy()` or `ICollection::orderBy()` methods. A collection function is used as an array argument, the first value is the function identifier (it is recommended using function's class name) and then function's arguments as other array values. Collection functions may be used together, also nest together, so you can reuse them.

```php
// collection function call
$collection->findBy([MyFunction::class, 'arg1', 'arg2']);

// or compose & nest the calls together
// ICollection::OR is also a collection function
$collection->findBy(
	[
		ICollection::OR,
		[MyFunction::class, 'arg1', 'arg2'],
		[AnotherFunction::class, 'arg3'],
	]
);
```

Functions are registered per repository. To do so, override `Repository::createCollectionFunction($name)` method to return your collection functions' instances.

```php
class UsersRepository extends Nextras\Orm\Repository\Repository
{
	// ...
	public function createCollectionFunction(string $name)
	{
		if ($name === MyFunction::class) {
			return new MyFunction();
		} else {
			return parent::createCollectionFunction($name);
		}
	}
}
```

#### Dbal Functions

Dbal collection functions have to implement `Nextras\Orm\Collection\Functions\IQueryBuilderFunction` interface. The only required method takes three arguments: `DbalQueryBuilderHelper` for easier user input processing, `QueryBuilder` for creating table joins, and a user input/function parameters.

Collection function has to return a `DbalExpressionResult` object. This object holds parts of SQL query which may be processed by Nextras Dbal's SqlProcessor. Because you are not adding SQL parts directly to QueryBuilder but rather return them in `DbalExpressionResult`, you may compose multiple collection functions together.

Let's see an example: a "Like" collection function; We want to compare a property (expression) via SQL's LIKE operator with a prefix comparison.

```php
$users->findBy(
	[LikeFunction::class, 'phone', '+420']
);
```

In the example we would like to use the custom `LikeFunction` to filter users by their phones that start with `+420` prefix. Our function will implement `IQueryBuilderFunction` interface and will receive `$args` with `phone` and `+420`. The column/property argument may be quite dynamic too. What if the user passes `address->zipcode` (i.e. a relationship expression) instead of a simple `phone`, such expression would require table joins; doing it all by hand would be difficult. Therefore, Orm provides `DbalQueryBuilderHelper` that will handle all this for you. Use `processPropertyExpr` method to obtain a `DbalExpressionResult` for the column/property argument. Then just append needed SQL to the returned expression, e.g. LIKE operator with a Dbal's argument. That's all!

```php
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;

final class LikeFunction implements IQueryBuilderFunction
{
	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args
	): DbalExpressionResult
	{
		// $args is for example ['phone', '+420']
		\assert(\count($args) === 2 && \is_string($args[0]) && \is_string($args[1]));

		$expression = $helper->processPropertyExpr($builder, $args[0]);
		return $expression->append('LIKE %like_', $args[1]);
	}
}
```

The helper processed value may not be just a column, but also another expression returned from another collection function.

If you need more advance operation than appending the expression, construct a new `DbalExpressionResult` object. Use Dbal's `%ex` modifier to expand the already processed expression. Some properties of the original expression result may be lost by creating a new expression result instance; if needed, pass the original's values as additional constructor parameters.

```php
$expression = $helper->processPropertyExpr($builder, $args[0]);
return new DbalExpressionResult(['SUBSTRING(%ex, 0, %i) = %s', $expression->args, \strlen($args[1]), $args[1]]);
```

#### Array Functions

Array collection functions have to implement `Netras\Orm\Collection\Functions\IArrayFunction` interface. It is different to Dbal's interface, because the filtering happens directly in PHP now. The only required method takes `ArrayCollectionHelper` for easier entity property processing, `IEntity` entity to check if it should (not) be filtered out, and user input/function parameters.

Array collection function returns a mixed value, the kind depends on which context it will be evaluated. The value will be interpreted as boolean in the filtering context to indicate if the entity should be filtered out; the value will be used for comparison of two entities in the ordering context (using the spaceship operator).

Let's see an example: a "Like" collection function; We want to compare any (property) expression to passed user-input value with a prefix comparison.

```php
use Nette\Utils\Strings;
use Nextras\Orm\Collection\Functions\IArrayFunction;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Entity\IEntity;

final class LikeFunction implements IArrayFunction
{
	public function processArrayExpression(ArrayCollectionHelper $helper, IEntity $entity, array $args)
	{
		\assert(\count($args) === 2 && \is_string($args[0]) && \is_string($args[1]));

		$value = $helper->getValue($entity, $args[0])->value;
		return Strings::startsWith($value, $args[1]);
	}
}
```

Our function implements the `IArrayFunction` interface and will receive helper objects & user-input. The same as in Dbal's example, the user property argument may vary from simple property access to traversing through relationship expression. Let's use a helper to get the property expression value holder, then read the value from the holder. Finally, we simply compare the value with the user-input argument by Nette's string helper.

<div class="note">

Postgres is case-sensitive, so you should apply the lower function & a functional index; These modifications are case-specific, therefore the LIKE functionality is not provided in Orm by default.
</div>
