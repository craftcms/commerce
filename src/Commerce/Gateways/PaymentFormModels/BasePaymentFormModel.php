<?php

namespace Commerce\Gateways\PaymentFormModels;

use Craft\BaseModel;

/**
 * Base Payment form model.
 *
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   Commerce\Gateways\PaymentFormModels
 * @since     1.1
 */
abstract class BasePaymentFormModel extends BaseModel
{

	/**
	 * @param $post
	 */
	public function populateModelFromPost($post)
	{
		foreach ($this->getAttributes() as $attr => $value)
		{
			$this->$attr = \Craft\craft()->request->getPost($attr);
		}
	}
}