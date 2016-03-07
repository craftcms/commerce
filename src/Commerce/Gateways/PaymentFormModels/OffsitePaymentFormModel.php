<?php

namespace Commerce\Gateways\PaymentFormModels;

/**
 * Payment form model. Used for validation of input, not directly persisted.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class OffsitePaymentFormModel extends BasePaymentFormModel
{

	/**
	 * Offsite gateways require no validation.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [];
	}

	/**
	 * Offsite gateways require no user submitted data.
	 *
	 * @return array
	 */
	protected function defineAttributes()
	{
		return [];
	}
}