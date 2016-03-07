<?php

namespace Commerce\Gateways\PaymentFormModels;

use Craft\AttributeType;

/**
 * Stripe Payment form model.
 *
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class StripePaymentFormModel extends BasePaymentFormModel
{
	public function populateModelFromPost($post)
	{
		parent::populateModelFromPost($post);
		if (isset($post['stripeToken']))
		{
			$this->token = 	$post['stripeToken'];
		}
	}

	/**
	 * @return array
	 */
	public function rules()
	{
		return [
			['token', 'required']
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{

		$date = date_create();
		date_modify($date, '+1 year');
		$defaultExpiry = date_format($date, 'm/Y');
		return [
			'firstName' => AttributeType::String,
			'lastName'  => AttributeType::String,
			'number'    => AttributeType::Number,
			'month'     => AttributeType::Number,
			'year'      => AttributeType::Number,
			'cvv'       => AttributeType::Number,
			'token'     => AttributeType::String,
			'expiry'     => [AttributeType::String, 'default' => $defaultExpiry],
		];
	}
}