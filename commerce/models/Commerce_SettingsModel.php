<?php
namespace Craft;

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
		$currencies = [
			'AFN' => 'AFN',
			'EUR' => 'EUR',
			'ALL' => 'ALL',
			'DZD' => 'DZD',
			'USD' => 'USD',
			'AOA' => 'AOA',
			'XCD' => 'XCD',
			'ARS' => 'ARS',
			'AMD' => 'AMD',
			'AWG' => 'AWG',
			'AUD' => 'AUD',
			'AZN' => 'AZN',
			'BSD' => 'BSD',
			'BHD' => 'BHD',
			'BDT' => 'BDT',
			'BBD' => 'BBD',
			'BYR' => 'BYR',
			'BZD' => 'BZD',
			'XOF' => 'XOF',
			'BMD' => 'BMD',
			'BTN' => 'BTN',
			'INR' => 'INR',
			'BOB' => 'BOB',
			'BOV' => 'BOV',
			'BAM' => 'BAM',
			'BWP' => 'BWP',
			'NOK' => 'NOK',
			'BRL' => 'BRL',
			'BND' => 'BND',
			'BGN' => 'BGN',
			'BIF' => 'BIF',
			'KHR' => 'KHR',
			'XAF' => 'XAF',
			'CAD' => 'CAD',
			'CVE' => 'CVE',
			'KYD' => 'KYD',
			'CLF' => 'CLF',
			'CLP' => 'CLP',
			'CNY' => 'CNY',
			'COP' => 'COP',
			'COU' => 'COU',
			'KMF' => 'KMF',
			'CDF' => 'CDF',
			'NZD' => 'NZD',
			'CRC' => 'CRC',
			'HRK' => 'HRK',
			'CUC' => 'CUC',
			'CUP' => 'CUP',
			'ANG' => 'ANG',
			'CZK' => 'CZK',
			'DKK' => 'DKK',
			'DJF' => 'DJF',
			'DOP' => 'DOP',
			'EGP' => 'EGP',
			'SVC' => 'SVC',
			'ERN' => 'ERN',
			'ETB' => 'ETB',
			'FKP' => 'FKP',
			'FJD' => 'FJD',
			'XPF' => 'XPF',
			'GMD' => 'GMD',
			'GEL' => 'GEL',
			'GHS' => 'GHS',
			'GIP' => 'GIP',
			'GTQ' => 'GTQ',
			'GBP' => 'GBP',
			'GNF' => 'GNF',
			'GYD' => 'GYD',
			'HTG' => 'HTG',
			'HNL' => 'HNL',
			'HKD' => 'HKD',
			'HUF' => 'HUF',
			'ISK' => 'ISK',
			'IDR' => 'IDR',
			'XDR' => 'XDR',
			'IRR' => 'IRR',
			'IQD' => 'IQD',
			'ILS' => 'ILS',
			'JMD' => 'JMD',
			'JPY' => 'JPY',
			'JOD' => 'JOD',
			'KZT' => 'KZT',
			'KES' => 'KES',
			'KPW' => 'KPW',
			'KRW' => 'KRW',
			'KWD' => 'KWD',
			'KGS' => 'KGS',
			'LAK' => 'LAK',
			'LVL' => 'LVL',
			'LBP' => 'LBP',
			'LSL' => 'LSL',
			'ZAR' => 'ZAR',
			'LRD' => 'LRD',
			'LYD' => 'LYD',
			'CHF' => 'CHF',
			'LTL' => 'LTL',
			'MOP' => 'MOP',
			'MKD' => 'MKD',
			'MGA' => 'MGA',
			'MWK' => 'MWK',
			'MYR' => 'MYR',
			'MVR' => 'MVR',
			'MRO' => 'MRO',
			'MUR' => 'MUR',
			'XUA' => 'XUA',
			'MXN' => 'MXN',
			'MXV' => 'MXV',
			'MDL' => 'MDL',
			'MNT' => 'MNT',
			'MAD' => 'MAD',
			'MZN' => 'MZN',
			'MMK' => 'MMK',
			'NAD' => 'NAD',
			'NPR' => 'NPR',
			'NIO' => 'NIO',
			'NGN' => 'NGN',
			'OMR' => 'OMR',
			'PKR' => 'PKR',
			'PAB' => 'PAB',
			'PGK' => 'PGK',
			'PYG' => 'PYG',
			'PEN' => 'PEN',
			'PHP' => 'PHP',
			'PLN' => 'PLN',
			'QAR' => 'QAR',
			'RON' => 'RON',
			'RUB' => 'RUB',
			'RWF' => 'RWF',
			'SHP' => 'SHP',
			'WST' => 'WST',
			'STD' => 'STD',
			'SAR' => 'SAR',
			'RSD' => 'RSD',
			'SCR' => 'SCR',
			'SLL' => 'SLL',
			'SGD' => 'SGD',
			'XSU' => 'XSU',
			'SBD' => 'SBD',
			'SOS' => 'SOS',
			'SSP' => 'SSP',
			'LKR' => 'LKR',
			'SDG' => 'SDG',
			'SRD' => 'SRD',
			'SZL' => 'SZL',
			'SEK' => 'SEK',
			'CHE' => 'CHE',
			'CHW' => 'CHW',
			'SYP' => 'SYP',
			'TWD' => 'TWD',
			'TJS' => 'TJS',
			'TZS' => 'TZS',
			'THB' => 'THB',
			'TOP' => 'TOP',
			'TTD' => 'TTD',
			'TND' => 'TND',
			'TRY' => 'TRY',
			'TMT' => 'TMT',
			'UGX' => 'UGX',
			'UAH' => 'UAH',
			'AED' => 'AED',
			'USN' => 'USN',
			'USS' => 'USS',
			'UYI' => 'UYI',
			'UYU' => 'UYU',
			'UZS' => 'UZS',
			'VUV' => 'VUV',
			'VEF' => 'VEF',
			'VND' => 'VND',
			'YER' => 'YER',
			'ZMK' => 'ZMK',
			'ZWL' => 'ZWL',
		];
		ksort($currencies, SORT_STRING);

		return $currencies;
	}
}