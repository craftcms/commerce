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
	 * Rounds the amount as per the currency minor unit information. Not passing
	 * a currency model results in rounding in default currency.
	 *
	 * @param float                                     $amount
	 * @param \Craft\Commerce_PaymentCurrencyModel|null $currency
	 *
	 * @return float
	 */
	public static function round($amount, $currency = null)
	{
		if (!$currency)
		{
			$defaultPaymentCurrency = \Craft\craft()->commerce_paymentCurrencies->getDefaultPaymentCurrency();
			$currency = \Craft\craft()->commerce_currencies->getCurrencyByIso($defaultPaymentCurrency->iso);
		}

		$decimals = $currency->minorUnit;

		return round($amount, $decimals);
	}


	public static function defaultDecimals()
	{
		$currency = \Craft\craft()->commerce_paymentCurrencies->getDefaultPaymentCurrencyIso();

		$decimals = \Craft\craft()->commerce_currencies->getCurrencyByIso($currency)->minorUnit;

		return $decimals;
	}
}