<?php
namespace Craft;

/**
 * Class Market_ChargeFormModel
 *
 * This Model is responsible for passing around a
 * form object which is used to make a new charge.
 *
 * @package Craft
 *
 */
class Market_CustomerModel extends BaseElementModel
{
	protected $elementType = 'Market_Customer';
	protected $modelRecord = 'Market_CustomerRecord';

	private $_apiData = NULL;

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
	 * @return mixed|string
	 */
	public function __toString()
	{
		// This is used in the elementType index template as the linked text column
		return $this->id;
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
	 * Charge Model Attributes
	 *
	 * @inheritDoc BaseRecord::defineAttributes()
	 *
	 * @return array
	 */
	protected function defineAttributes()
	{
		return array_merge(parent::defineAttributes(), array(

			/**
			 * Required fields on new charge
			 */
			'stripeId'        => AttributeType::String,
			'amount'          => AttributeType::Number,
			//TODO: Fill currency enum values dynamically based on https://support.stripe.com/questions/which-currencies-does-stripe-support
			'currency'        => array(AttributeType::Enum, 'values' => "AUD,USD"),

			/**
			 * Optional fields on new charge
			 */
			'description'     => AttributeType::String,
			'email'           => AttributeType::String,
			'metadata'        => AttributeType::Mixed,

			/**
			 * Only exist on a saved customer
			 */
			'created'         => AttributeType::DateTime,
			'discount'        => AttributeType::Mixed,
			'account_balance' => AttributeType::Number,
			'delinquent'      => AttributeType::String,
			'livemode'        => AttributeType::Bool,
		));
	}

	public function getData()
	{
		if ($this->_apiData == NULL) {
			$this->_loadStripeData();
		}

		return $this;
	}

	private function _loadStripeData()
	{
		$this->_apiData = \Market\Market::app()['stripe']->customers()->find(array(
			'id' => $this->stripeId
		));

		foreach ($this->_apiData as $key => $val) {
			if (in_array($key, $this->attributeNames()) && $key != 'id') {
				$this->$key = $val;
			}
		}

	}

}