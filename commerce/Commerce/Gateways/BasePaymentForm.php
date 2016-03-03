<?php
namespace Craft;

use Omnipay\Common\Helper as OmnipayHelper;

/**
 * Payment form model. Used for validation of input, not directly persisted.
 *
 * @property string $firstName
 * @property string $lastName
 * @property int    $month
 * @property int    $year
 * @property int    $cvv
 * @property int    $number
 * @property int    $token
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_PaymentFormModel extends BaseModel
{

	public static function populateModel($values)
	{
		// Let's be nice and allow 'stripeToken' to be used as 'token', since it is the checkout.js default.
		if(isset($values['stripeToken']) && $values['stripeToken'] != ""){
			$values['token'] = $values['stripeToken'];
		}

		return parent::populateModel($values);
	}

	/**
	 * @return array
	 */
	public function rules()
	{
		return [
			['firstName, lastName, month, year, cvv, number', 'required'],
			[
				'month',
				'numerical',
				'integerOnly' => true,
				'min'         => 1,
				'max'         => 12
			],
			[
				'year',
				'numerical',
				'integerOnly' => true,
				'min'         => date('Y'),
				'max'         => date('Y') + 12
			],
			['cvv', 'numerical', 'integerOnly' => true],
			['cvv', 'length', 'min' => 3, 'max' => 4],
			['number', 'numerical', 'integerOnly' => true],
			['number', 'length', 'max' => 19],
			['number', 'creditCardLuhn']
		];
	}

	/**
	 * @param $attribute
	 * @param $params
	 */
	public function creditCardLuhn($attribute, $params)
	{
		if (!OmnipayHelper::validateLuhn($this->$attribute))
		{
			$this->addError($attribute, Craft::t('Not a valid Credit Card Number'));
		}
	}

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{
		return [
			'firstName' => AttributeType::String,
			'lastName'  => AttributeType::String,
			'number'    => AttributeType::Number,
			'month'     => AttributeType::Number,
			'year'      => AttributeType::Number,
			'cvv'       => AttributeType::Number,
			'token'     => AttributeType::String
		];
	}
}