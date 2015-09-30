<?php

namespace Commerce\Adjusters;

use Craft\Commerce_LineItemModel;
use Craft\Commerce_OrderAdjustmentModel;
use Craft\Commerce_OrderModel;
use Craft\Commerce_ShippingRuleModel;

/**
 * Tax Adjustments
 *
 * Class Commerce_ShippingAdjuster
 *
 * @package Commerce\Adjusters
 */
class Commerce_ShippingAdjuster implements Commerce_AdjusterInterface
{
	const ADJUSTMENT_TYPE = 'Shipping';

	/**
	 * @param Commerce_OrderModel      $order
	 * @param Commerce_LineItemModel[] $lineItems
	 *
	 * @return \Craft\Commerce_OrderAdjustmentModel[]
	 */
	public function adjust (Commerce_OrderModel &$order, array $lineItems = [])
	{
		$shippingMethod = \Craft\craft()->commerce_shippingMethod->getById($order->shippingMethodId);

		if (!$shippingMethod->id)
		{
			return [];
		}

		$adjustments = [];

		if ($rule = \Craft\craft()->commerce_shippingMethod->getMatchingRule($order, $shippingMethod))
		{
			//preparing model
			$adjustment = new Commerce_OrderAdjustmentModel;
			$adjustment->type = self::ADJUSTMENT_TYPE;
			$adjustment->name = $shippingMethod->name;
			$adjustment->description = $this->getDescription($rule);
			$adjustment->orderId = $order->id;
			$adjustment->optionsJson = $rule->attributes;

			//checking items tax categories
			$weight = $qty = $price = 0;
			$itemShippingTotal = 0;
			$freeShippingAmount = 0;
			foreach ($lineItems as $item)
			{
				$weight += $item->qty * $item->weight;
				$qty += $item->qty;
				$price += $item->getSubtotalWithSale();

				$item->shippingCost = ($item->getSubtotalWithSale() * $rule->percentageRate) + $rule->perItemRate + ($item->weight * $rule->weightRate);
				$itemShippingTotal += $item->shippingCost * $item->qty;

				if ($item->purchasable->product->freeShipping)
				{
					$freeShippingAmount = $freeShippingAmount + $item->shippingCost;
					$item->shippingCost = 0;
				}
			}

			//amount for displaying in adjustment
			$amount = $rule->baseRate + $itemShippingTotal - $freeShippingAmount;
			$amount = max($amount, $rule->minRate * 1);

			if ($rule->maxRate * 1)
			{
				$amount = min($amount, $rule->maxRate * 1);
			}

			$adjustment->amount = $amount;

			//real shipping base rate (can be a bit artificial because it counts min and max rate as well, but in general it equals to baseRate)
			$order->baseShippingCost = $amount - $itemShippingTotal - $freeShippingAmount;

			$adjustments[] = $adjustment;
		}

		return $adjustments;
	}

	/**
	 * @param Commerce_ShippingRuleModel $rule
	 *
	 * @return string "1$ and 5% per item and 10$ base rate"
	 */
	private function getDescription (Commerce_ShippingRuleModel $rule)
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