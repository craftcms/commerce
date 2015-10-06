<?php
namespace Craft;

use Omnipay\Common\Currency;
/**
 * Settings model.
 *
 * @property string $defaultCurrency
 * @property string $paymentMethod
 * @property string $weightUnits
 * @property string $dimensionUnits
 * @property string $emailSenderAddress
 * @property string $emailSenderName
 * @property string $orderPdfPath
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_SettingsModel extends BaseModel
{
	/**
	 * @var
	 */
	public $emailSenderAddressPlaceholder;
	/**
	 * @var
	 */
	public $emailSenderNamePlaceholder;

	/**
	 * @return array
	 */
	public function defineAttributes ()
	{
		return [
			'defaultCurrency'    => [
				AttributeType::String,
				'default'  => 'USD',
				'required' => true
			],
			'paymentMethod'      => [
				AttributeType::Enum,
				'values'   => ['authorize', 'purchase'],
				'required' => true,
				'default'  => 'purchase'
			],
			'weightUnits'        => [
				AttributeType::String,
				'default' => 'g'
			],
			'dimensionUnits'     => [
				AttributeType::String,
				'default' => 'mm'
			],
			'emailSenderAddress' => [AttributeType::String],
			'emailSenderName'    => [AttributeType::String],
			'orderPdfPath'       => [AttributeType::String]
		];
	}

	/**
	 * @return array
	 */
	public function getPaymentMethodOptions ()
	{
		return [
			'authorize' => 'Authorize Only',
			'purchase'  => 'Purchase (Authorize and Capture)',
		];
	}

	/**
	 * @return array
	 */
	public function getWeightUnitsOptions ()
	{
		return [
			'g'  => 'Grams (g)',
			'kg' => 'Kilograms (kg)',
			'lb' => 'Pounds (lb)',
		];
	}

	/**
	 * @return array
	 */
	public function getDimensionUnits ()
	{
		return [
			'mm' => 'Millimeters (mm)',
			'cm' => 'Centimeters (cm)',
			'm'  => 'Metres (m)',
			'ft' => 'Feet (ft)',
			'in' => 'Inches (in)',
		];
	}

	/**
	 * @return array
	 */
	public function getCurrencies ()
	{

		$currencies = Currency::all();

		foreach ($currencies as $key => &$value) {
			$value = $key;
		}

		ksort($currencies, SORT_STRING);

		return $currencies;
	}
}