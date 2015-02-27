<?php
namespace Craft;

/**
 * Class Market_ChargeFormModel
 *
 * @package Craft
 *
 * @property int                 id
 * @property int                 userId
 * @property string              email
 *
 * @property Market_AddressModel addresses
 */
class Market_CustomerModel extends BaseElementModel
{
	/**
	 * Returns whether the current user can edit the element.
	 *
	 * @return bool
	 */
	public function isEditable()
	{
		return true;
	}

	/**
	 * Returns the element's CP edit URL.
	 *
	 * @return string|false
	 */
	public function getCpEditUrl()
	{
		return UrlHelper::getCpUrl('market/customer/' . $this->id);
	}

	/**
	 * @return Market_AddressModel[]
	 */
	public function getAddresses()
	{
		return craft()->market_address->getByCustomerId($this->id);
	}

	/**
	 * Charge Model Attributes
	 *
	 * @inheritDoc BaseRecord::defineAttributes()
	 *
	 * @return array
	 */
	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), [
			'userId' => AttributeType::Number,
			'email'  => AttributeType::String,
		]);
	}
}