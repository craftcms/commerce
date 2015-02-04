<?php
namespace Market\Charge;

use Cartalyst\Stripe\Api\Exception\StripeException;
use Craft\BaseElementModel;
use Craft\Market_ChargeRecord as ChargeRecord;

class Creator
{

	/** @var \CModel $_charge */
	private $_charge;

	function __construct()
	{
	}

	/**
	 * Creator of Stripe Charges.
	 * This accepts a Yii CModel that responds to the following
	 * attributes: card (stripeToken), currency, amount
	 *
	 * @param \CModel $charge
	 *
	 * @return mixed
	 * @throws \Market\Exception\BaseException
	 */
	public function create(BaseElementModel $charge)
	{
		$this->_charge = $charge;
		$isNewCharge   = !$charge->id;

		try {
			if ($isNewCharge) {
				$this->createNew();
			} else {
				//TODO: Update Charge?
			}
		} catch (StripeException $e) {
			$error = $e->getMessage();
			$charge->addError('stripe', $error);
		}

		return $this->_charge;
	}

	/**
	 *
	 */
	private function createNew()
	{
		if (\Craft\craft()->elements->saveElement($this->_charge)) {
			$chargeRequest           = $this->buildChargeRequestWithDefaults();
			$stripeCharge            = \Market\market()['stripe']->charges()->create($chargeRequest);
			$chargeRecord            = new ChargeRecord();
			$this->_charge->stripeId = $stripeCharge['id'];
			$chargeRecord->id        = $this->_charge->id;
			$chargeRecord->stripeId  = $this->_charge->stripeId;
			$chargeRecord->amount    = $this->_charge->amount;
			//TODO: Set other attributes like customer/user etc
			$chargeRecord->save();
		} else {
			// Fail loudly if we cannot make an element from this Model
			throw new \Market\Exception\BaseException("Could not save the Charge element");
		}
	}

	/**
	 * Builds a Stripe array for the create request
	 *
	 * @return array
	 */
	private function buildChargeRequestWithDefaults()
	{
		$card       = $this->_charge->card;
		$currency   = $this->_charge->currency;
		$amount     = $this->_charge->amount;
		$capture    = $this->_charge->capture;
		$chargeData = compact('card', 'currency', 'amount', 'capture');

		return $chargeData;
	}
}