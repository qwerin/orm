<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\IArrayAggregator;
use Nextras\Orm\Collection\Aggregations\IDbalAggregator;
use Nextras\Orm\Collection\Functions\Result\ArrayExpressionResult;
use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use function assert;
use function count;
use function is_array;
use function is_string;


abstract class BaseAggregateFunction implements IArrayFunction, IQueryBuilderFunction
{
	/** @var literal-string */
	private $sqlFunction;


	/**
	 * @param literal-string $sqlFunction
	 */
	protected function __construct(string $sqlFunction)
	{
		$this->sqlFunction = $sqlFunction;
	}


	/**
	 * @param array<number> $values
	 * @return number|null
	 */
	abstract protected function calculateAggregation(array $values);


	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?IArrayAggregator $aggregator = null
	): ArrayExpressionResult
	{
		assert(count($args) === 1 && is_string($args[0]));

		$valueReference = $helper->getValue($entity, $args[0], $aggregator);
		if ($valueReference->aggregator === null) {
			throw new InvalidArgumentException('Aggregation has to be called over has many relationship.');
		}
		assert(is_array($valueReference->value));

		return new ArrayExpressionResult(
			$this->calculateAggregation($valueReference->value),
			null,
			null
		);
	}


	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?IDbalAggregator $aggregator = null
	): DbalExpressionResult
	{
		assert(count($args) === 1 && is_string($args[0]));

		if ($aggregator !== null) {
			throw new InvalidStateException("Cannot apply two aggregations simultaneously.");
		}

		$aggregator = new class implements IDbalAggregator {
			/** @var literal-string */
			public $sqlFunction;


			public function getAggregateKey(): string
			{
				return '_' . $this->sqlFunction;
			}


			public function aggregateExpression(
				QueryBuilder $queryBuilder,
				DbalExpressionResult $expression
			): DbalExpressionResult
			{
				return new DbalExpressionResult(
					"{$this->sqlFunction}($expression->expression)",
					$expression->args,
					$expression->joins,
					$expression->groupBy,
					null,
					true,
					null,
					null
				);
			}
		};
		$aggregator->sqlFunction = $this->sqlFunction;

		return $helper->processPropertyExpr($builder, $args[0], $aggregator)->applyAggregator($builder);
	}
}
