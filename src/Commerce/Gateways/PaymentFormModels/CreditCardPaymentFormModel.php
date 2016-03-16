<?php

namespace Commerce\Gateways\PaymentFormModels;

use Craft\AttributeType;
use Omnipay\Common\Helper as OmnipayHelper;

/**
 * Base Payment form model.
 *
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.1
 */
class CreditCardPaymentFormModel extends BasePaymentFormModel
{
	public function populateModelFromPost($post)
	{
		parent::populateModelFromPost($post);

		$this->number = preg_replace('/\D/', '', isset($post['number']) ? $post['number'] : '');

		if (isset($post['expiry']))
		{
			$expiry = explode("/", $post['expiry']);

			if (isset($expiry[0]))
			{
				$this->month = trim($expiry[0]);
			}

			if (isset($expiry[1]))
			{
				$this->year = trim($expiry[1]);
			}
		}

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
			$this->addError($attribute, \Craft\Craft::t('Not a valid Credit Card Number'));
		}
	}

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{

		$date = date_create();
		date_modify($date, '+1 year');
		$defaultExpiry = date_format($date, 'm/Y');
		$defaultMonth = date_format($date, 'm');
		$defaultYear = date_format($date, 'Y');
		return [
			'firstName' => AttributeType::String,
			'lastName'  => AttributeType::String,
			'number'    => AttributeType::Number,
			'month'     => [AttributeType::Number, 'default' => $defaultMonth],
			'year'      => [AttributeType::Number, 'default' => $defaultYear],
			'cvv'       => AttributeType::Number,
			'token'     => AttributeType::String,
			'expiry'     => [AttributeType::String, 'default' => $defaultExpiry],
		];
	}
}