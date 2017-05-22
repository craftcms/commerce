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
 * @package   Commerce\Gateways\PaymentFormModels\
 * @since     1.1
 */
class WorldpayJsonPaymentFormModel extends BasePaymentFormModel
{

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
        return [
            'token'     => AttributeType::String
        ];
    }
}