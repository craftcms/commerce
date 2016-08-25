<?php

namespace Commerce\Helpers;

use Omnipay\Common\Currency;

/**
 * Class CommerceCurrencyHelper
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   Commerce\Helpers
 * @since     1.0
 */
class CommerceCurrencyHelper
{

	/**
	 * Rounds the amount as per the omnipay decimal information. Not passing
	 * a currency model results in rounding in default currency.
	 *
	 * @param float                              $amount
	 * @param \Craft\Commerce_CurrencyModel|null $currency
	 *
	 * @return float
	 */
	public static function round($amount, $currency = null)
	{
		if (!$currency)
		{
			$currency = \Craft\craft()->commerce_settings->getSettings()->defaultCurrency;
		}

		$decimals = Currency::find($currency)->getDecimals();

		return round($amount, $decimals);
	}


	public static function defaultDecimals()
	{
		$currency = \Craft\craft()->commerce_settings->getSettings()->defaultCurrency;

		$decimals = Currency::find($currency)->getDecimals();

		return $decimals;
	}
}