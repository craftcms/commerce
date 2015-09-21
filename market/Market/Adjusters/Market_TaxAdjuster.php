<?php

namespace Market\Adjusters;

use Craft\Market_AddressModel;
use Craft\Market_LineItemModel;
use Craft\Market_OrderAdjustmentModel;
use Craft\Market_OrderModel;
use Craft\Market_TaxRateModel;
use Craft\Market_TaxZoneModel;

/**
 * Tax Adjustments
 *
 * Class Market_TaxAdjuster
 *
 * @package Market\Adjusters
 */
class Market_TaxAdjuster implements Market_AdjusterInterface
{
	const ADJUSTMENT_TYPE = 'Tax';

	/**
	 * @param Market_OrderModel      $order
	 * @param Market_LineItemModel[] $lineItems
	 *
	 * @return \Craft\Market_OrderAdjustmentModel[]
	 */
	public function adjust(Market_OrderModel &$order, array $lineItems = [])
	{
        $shippingAddress = \Craft\craft()->market_address->getAddressById($order->shippingAddressId);
        if (!$shippingAddress->id) {
            $shippingAddress = null;
        }

        $adjustments = [];
        $taxRates    = \Craft\craft()->market_taxRate->getAll([
            'with' => ['taxZone', 'taxZone.countries', 'taxZone.states.country'],
        ]);

		/** @var Market_TaxRateModel $rate */
		foreach ($taxRates as $rate) {
			if ($adjustment = $this->getAdjustment($order, $lineItems, $shippingAddress, $rate)) {
				$adjustments[] = $adjustment;
			}
		}

		return $adjustments;
	}

	/**
	 * @param Market_OrderModel      $order
	 * @param Market_LineItemModel[] $lineItems
	 * @param Market_AddressModel    $address
	 * @param Market_TaxRateModel    $taxRate
	 *
	 * @return Market_OrderAdjustmentModel|false
	 */
	private function getAdjustment(Market_OrderModel $order, array $lineItems, Market_AddressModel $address = null, Market_TaxRateModel $taxRate)
	{
		$zone = $taxRate->taxZone;

		//preparing model
		$adjustment              = new Market_OrderAdjustmentModel;
		$adjustment->type        = self::ADJUSTMENT_TYPE;
		$adjustment->name        = $taxRate->name;
		$adjustment->description = $taxRate->rate * 100 . '%' . ($taxRate->include ? ' inc' : '');
		$adjustment->orderId     = $order->id;
		$adjustment->optionsJson = $taxRate->attributes;

		//checking address
        if (!$this->matchAddress($address, $zone)) {
			if ($taxRate->include) {
				//excluding taxes included in price
				foreach ($lineItems as $item) {
					if ($item->taxCategoryId == $taxRate->taxCategoryId) {
						$item->tax += -($item->getPriceWithoutShipping()-($item->getPriceWithoutShipping()/(1+$taxRate->rate)))  * $item->qty;
					}
				}
			}

			return false;
		}

		//checking items tax categories
		$itemsMatch = false;
		foreach ($lineItems as $item) {

			if ($item->taxCategoryId == $taxRate->taxCategoryId) {
				if (!$taxRate->include) {
					$itemtax = $taxRate->rate * $item->getPriceWithoutShipping() * $item->qty;
				}else{
					$itemtax = ($item->getPriceWithoutShipping()-($item->getPriceWithoutShipping()/(1+$taxRate->rate))) * $item->qty;
				}

				$adjustment->amount += $itemtax;

				if (!$taxRate->include) {
					$item->tax += $itemtax;
				}

				$itemsMatch = true;
			}
		}

		return $itemsMatch ? $adjustment : false;
	}

    /**
     * @param Market_AddressModel $address
     * @param Market_TaxZoneModel $zone
     * @return bool
     */
    private function matchAddress(Market_AddressModel $address = null, Market_TaxZoneModel $zone)
    {
        //when having no address check default tax zones only
        if(!$address) {
            return $zone->default;
        }

        if ($zone->countryBased) {
            $countriesIds = $zone->getCountriesIds();

            if (in_array($address->countryId, $countriesIds)) {
                return true;
            }
        } else {
            foreach ($zone->states as $state) {
                if ($state->country->id == $address->countryId && $state->name == $address->getStateText()) {
                    return true;
                }
            }
        }
        return false;
    }
}