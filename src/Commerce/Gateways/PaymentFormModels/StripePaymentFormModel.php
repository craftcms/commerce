<?php

namespace Commerce\Gateways\PaymentFormModels;

/**
 * Stripe Payment form model.
 *
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   Commerce\Gateways\PaymentFormModels\
 * @since     1.1
 */
class StripePaymentFormModel extends CreditCardPaymentFormModel
{
	public function populateModelFromPost($post)
	{
		parent::populateModelFromPost($post);
		if (isset($post['stripeToken']))
		{
			$this->token = $post['stripeToken'];
		}
	}

	public function rules()
	{
		if (empty($this->token))
		{
			return parent::rules();
		}
		else
		{
			return [];
		}
	}
}