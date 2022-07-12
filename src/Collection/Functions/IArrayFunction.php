<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Aggregations\IArrayAggregator;
use Nextras\Orm\Collection\Functions\Result\ArrayExpressionResult;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Entity\IEntity;


/**
 * Collection function implementation for ArrayCollection.
 * Processes expression and reuse its result for further evaluation.
 */
interface IArrayFunction
{
	/**
	 * Returns a value depending on values of entity; the expression passed by args is evaluated during this method
	 * execution.
	 * Usually returns a boolean for filtering evaluation.
	 * @phpstan-param array<int|string, mixed> $args
	 * @phpstan-param IArrayAggregator<mixed>|null $aggregator
	 */
	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?IArrayAggregator $aggregator = null
	): ArrayExpressionResult;
}
