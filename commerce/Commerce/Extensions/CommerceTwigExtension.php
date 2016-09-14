<?php
namespace Commerce\Extensions;

/**
 * Class CommerceTwigExtension
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   Commerce\Extensions
 * @since     1.0
 */
class CommerceTwigExtension extends \Twig_Extension
{

	/**
	 * @return string
	 */
	public function getName()
	{
		return 'Craft Commerce Twig Extension';
	}

	/**
	 * @return mixed
	 */
	public function getFilters()
	{
		$returnArray['json_encode_filtered'] = new \Twig_Filter_Method($this, 'jsonEncodeFiltered');

		$returnArray['currencyConvert'] = new \Twig_Filter_Method($this, 'currencyCovert');
		$returnArray['currencyFormat'] = new \Twig_Filter_Method($this, 'currencyFormat');
		$returnArray['currencyConvertFormat'] = new \Twig_Filter_Method($this, 'currencyCovertFormat');


		return $returnArray;
	}

	/**
	 * Converts an amount into the other payment currency as per the rate setup in payment currencies.
	 * @param $amount
	 * @param $currency
	 *
	 * @return float
	 */
	public function currencyCovert($amount, $currency)
	{
		$this->_validatePaymentCurrency($currency);

		return \Craft\craft()->commerce_paymentCurrencies->convert($amount, $currency);
	}

	/**
	 * Formats an amount as a currency based on the current locle
	 *
	 * @param      $amount
	 * @param      $currency
	 * @param bool $stripZeroCents
	 *
	 * @return mixed
	 */
	public function currencyFormat($amount, $currency, $stripZeroCents = false)
	{
		$this->_validatePaymentCurrency($currency);

		return \Craft\craft()->numberFormatter->formatCurrency($amount, $currency, $stripZeroCents);
	}

	/**
	 * Both converts into another payment currency and formats an amount as a currency.
	 *
	 * @param      $amount
	 * @param      $currency
	 * @param bool $stripZeroCents
	 *
	 * @return mixed
	 */
	public function currencyCovertFormat($amount, $currency, $stripZeroCents = false)
	{
		$this->_validatePaymentCurrency($currency);
		$amount = $this->currencyCovert($amount, $currency);

		return $this->currencyFormat($amount, $currency, $stripZeroCents);
	}

	public function jsonEncodeFiltered($input)
	{
		$array = $this->recursiveSanitizeArray($input);

		return json_encode($array);
	}

	private function recursiveSanitizeArray($array)
	{
		$finalArray = [];

		foreach ($array as $key => $value)
		{
			$newKey = self::sanitize($key);

			if (is_array($value))
			{
				$finalArray[$newKey] = $this->recursiveSanitizeArray($value);
			}
			else
			{
				$finalArray[$newKey] = self::sanitize($value);
			}
		}

		return $finalArray;
	}

	public static function sanitize($input)
	{
		$sanitized = $input;

		if (!is_int($sanitized))
		{
			$sanitized = filter_var($sanitized, FILTER_SANITIZE_SPECIAL_CHARS);
		}
		else
		{
			$newValue = filter_var($sanitized, FILTER_SANITIZE_SPECIAL_CHARS);

			if (is_numeric($newValue))
			{
				$sanitized = intval($newValue);
			}
			else
			{
				$sanitized = $newValue;
			}
		}

		return $sanitized;
	}

	private function _validatePaymentCurrency($currency)
	{
		$currency = \Craft\craft()->commerce_paymentCurrencies->getPaymentCurrencyByIso($currency);

		if (!$currency)
		{
			throw new \Twig_Error(\Craft\Craft::t('Not a valid payment currency code'));
		}
	}
}
