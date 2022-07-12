<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nextras\Orm\Collection\Functions\Result\DbalExpressionResult;


class CompareGreaterThanFunction extends BaseCompareFunction
{
	protected function evaluateInPhp($sourceValue, $targetValue): bool
	{
		return $sourceValue > $targetValue;
	}


	protected function evaluateInDb(DbalExpressionResult $expression, $value, string $modifier): DbalExpressionResult
	{
		return $expression->append("> $modifier", $value);
	}
}
