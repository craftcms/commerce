<?php

namespace Commerce\Gateways\PaymentFormModels;

use Craft\AttributeType;
use Omnipay\Common\Helper as OmnipayHelper;

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
			['token', 'required'],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{
		return [
			'firstName' => AttributeType::String,
			'lastName'  => AttributeType::String,
		];
	}
}