<?php

namespace Market\Adjusters;

use Craft\Market_LineItemModel;
use Craft\Market_OrderAdjustmentModel;
use Craft\Market_OrderModel;
use Craft\Market_ShippingRuleModel;

/**
 * Tax Adjustments
 *
 * Class Market_ShippingAdjuster
 *
 * @package Market\Adjusters
 */
class Market_ShippingAdjuster implements Market_AdjusterInterface
{
	const ADJUSTMENT_TYPE = 'Shipping';

	/**
	 * @param Market_OrderModel      $order
	 * @param Market_LineItemModel[] $lineItems
	 *
	 * @return \Craft\Market_OrderAdjustmentModel[]
	 */
	public function adjust (Market_OrderModel &$order, array $lineItems = [])
	{
		$shippingMethod = \Craft\craft()->market_shippingMethod->getById($order->shippingMethodId);

		if (!$shippingMethod->id)
		{
			return [];
		}

		$adjustments = [];

		if ($rule = \Craft\craft()->market_shippingMethod->getMatchingRule($order, $shippingMethod))
		{
			//preparing model
			$adjustment = new Market_OrderAdjustmentModel;
			$adjustment->type = self::ADJUSTMENT_TYPE;
			$adjustment->name = $shippingMethod->name;
			$adjustment->description = $this->getDescription($rule);
			$adjustment->orderId = $order->id;
			$adjustment->optionsJson = $rule->attributes;

			//checking items tax categories
			$weight = $qty = $price = 0;
			$itemShippingTotal = 0;
			foreach ($lineItems as $item)
			{
				$weight += $item->qty * $item->weight;
				$qty += $item->qty;
				$price += $item->getSubtotalWithSale();

				$item->shippingCost = ($item->getSubtotalWithSale() * $rule->percentageRate) + $rule->perItemRate + ($item->weight * $rule->weightRate);
				$itemShippingTotal += $item->shippingCost * $item->qty;

				if ($item->purchasable->product->freeShipping)
				{
					$item->shippingCost = 0;
				}
			}

			//amount for displaying in adjustment
			$amount = $rule->baseRate + $itemShippingTotal;
			$amount = max($amount, $rule->minRate * 1);

			if ($rule->maxRate * 1)
			{
				$amount = min($amount, $rule->maxRate * 1);
			}

			$adjustment->amount = $amount;

			//real shipping base rate (can be a bit artificial because it counts min and max rate as well, but in general it equals to baseRate)
			$order->baseShippingCost = $amount - $itemShippingTotal;

			$adjustments[] = $adjustment;
		}

		return $adjustments;
	}

	/**
	 * @param Market_ShippingRuleModel $rule
	 *
	 * @return string "1$ and 5% per item and 10$ base rate"
	 */
	private function getDescription (Market_ShippingRuleModel $rule)
	{
		$description = '';
		if ($rule->perItemRate || $rule->percentageRate)
		{
			if ($rule->perItemRate)
			{
				$description .= $rule->perItemRate * 1 .'$ ';
			}

			if ($rule->percentageRate)
			{
				if ($rule->perItemRate)
				{
					$description .= 'and ';
				}

				$description .= $rule->percentageRate * 100 .'% ';
			}

			$description .= 'per item ';
		}

		if ($rule->baseRate)
		{
			if ($description)
			{
				$description .= 'and ';
			}
			$description .= $rule->baseRate * 1 .'$ base rate';
		}

		return $description;
	}
}