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
class Market_ChargeModel extends BaseElementModel
{
	protected $elementType = 'Market_Charge';

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
		return UrlHelper::getCpUrl('market/charges/' . $this->id);
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
			'stripeId'              => AttributeType::String,
			'amount'                => AttributeType::Number,
			//TODO: Fill currency enum values dynamically based on https://support.stripe.com/questions/which-currencies-does-stripe-support
			'currency'              => array(AttributeType::Enum, 'values' => "AUD,USD"),
			'card'                  => AttributeType::String, // or customer
			'customer'              => AttributeType::String, // or card
			'capture'               => array(AttributeType::Bool, 'default' => true),

			/**
			 * Optional fields on new charge
			 */
			'description'           => AttributeType::String,
			'metadata'              => AttributeType::Mixed,
			'statement_description' => AttributeType::String,
			'receipt_email'         => AttributeType::String,
			// Application fee not applicable unless we use sub stripe accounts w/ oauth
			// 'application_fee' => AttributeType::Number,

			/**
			 * These attributes only exist on a saved charge
			 */
			'created'               => AttributeType::DateTime,
			'paid'                  => AttributeType::Bool,
			'captured'              => AttributeType::Bool,
			'refunded'              => AttributeType::Bool,
			'refunds'               => AttributeType::Mixed,
			'amount_refunded'       => AttributeType::Number,
			'balance_transaction'   => AttributeType::String,
			'failure_message'       => AttributeType::String,
			'failure_code'          => AttributeType::String,
			'fraud_details'         => AttributeType::Mixed,
			'invoice'               => AttributeType::String,
			'dispute'               => AttributeType::Mixed,
			'receipt_number'        => AttributeType::String,
			'livemode'              => AttributeType::Bool,
		));
	}

	/**
	 * Returns the field layout used by this element.
	 *
	 * @return FieldLayoutModel|null
	 */
	public function getFieldLayout()
	{
		//return $calendar->getFieldLayout();
	}

	public function getCardFontCode()
	{
		$this->getData();
		$brand = "";
		switch ($this->card['brand']) {
			case "Visa":
				$brand = "fa-cc-visa";
				break;
			case "American Express":
				$brand = "fa-cc-amex";
				break;
			case "MasterCard":
				$brand = "fa-cc-mastercard";
				break;
			case "Discover":
				$brand = "fa-cc-discover";
				break;
			case "JCB":
				$brand = "fa-credit-card";
				break;
			case "Diners Club":
				$brand = "fa-credit-card";
				break;
			case "Unknown":
				$brand = "fa-credit-card";
				break;
			default:
				$brand = "fa-credit-card";
		}

		return $brand;
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
		$this->_apiData = \Market\Market::app()['stripe']->charges()->find(array(
			'id' => $this->stripeId
		));

		foreach ($this->_apiData as $key => $val) {
			if (in_array($key, $this->attributeNames()) && $key != 'id') {
				$this->$key = $val;
			}
		}

	}

	public function getCreated()
	{
		$this->_loadStripeData();

		return $this->created;
	}

}