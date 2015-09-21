<?php
namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_CustomerModel
 *
 * @package Craft
 *
 * @property int                   id
 * @property int                   userId
 * @property string                email
 * @property int                   lastUsedBillingAddressId
 * @property int                   lastUsedShippingAddressId
 *
 * @property Market_AddressModel[] addresses
 * @property Market_OrderModel[]   orders
 * @property UserModel             user
 */
class Market_CustomerModel extends BaseModel
{
	use Market_ModelRelationsTrait;

	/**
	 * Returns whether the current user can edit the element.
	 *
	 * @return bool
	 */
	public function isEditable ()
	{
		return true;
	}

	/**
	 * Returns the element's CP edit URL.
	 *
	 * @return string|false
	 */
	public function getCpEditUrl ()
	{
		return UrlHelper::getCpUrl('market/customers/'.$this->id);
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
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