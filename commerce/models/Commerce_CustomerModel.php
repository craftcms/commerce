<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Customer model.
 *
 * @property int                     $id
 * @property int                     $userId
 * @property string                  $email
 * @property int                     $lastUsedBillingAddressId
 * @property int                     $lastUsedShippingAddressId
 *
 * @property Commerce_AddressModel[] $addresses
 * @property Commerce_OrderModel[]   $orders
 * @property UserModel               $user
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_CustomerModel extends BaseModel
{
	use Commerce_ModelRelationsTrait;

	/**
	 * Returns whether the current user can edit the element.
	 *
	 * @return bool
	 */
	public
	function isEditable ()
	{
		return true;
	}

	/**
	 * Returns the element's CP edit URL.
	 *
	 * @return string|false
	 */
	public
	function getCpEditUrl ()
	{
		return UrlHelper::getCpUrl('commerce/customers/'.$this->id);
	}

	/**
	 * @return array
	 */
	protected
	function defineAttributes ()
	{
		return array_merge(parent::defineAttributes(), [
			'id'                        => AttributeType::Number,
			'userId'                    => AttributeType::Number,
			'email'                     => AttributeType::String,
			'lastUsedBillingAddressId'  => AttributeType::Number,
			'lastUsedShippingAddressId' => AttributeType::Number
		]);
	}
}