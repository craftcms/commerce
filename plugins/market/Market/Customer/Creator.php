<?php
namespace Market\Charge;

use Cartalyst\Stripe\Api\Exception\StripeException;

class Creator
{
	/** @var $listener callback object */
	protected $listener;

	/** @var \CModel $_charge */
	private $_customer;

	/**
	 * The creator of a change will let the listener
	 * know if a charge failed or succeeded
	 *
	 * @param $listener
	 */
	function __construct($listener)
	{
		$this->listener = $listener;
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
	public function create(\CModel $customer)
	{
		$this->_customer = $customer;
		$isNewCharge     = !$customer->id;

		try {
			if ($isNewCustomer) {
				$this->createNew();
			} else {
				//TODO: Update Customer?
			}
		} catch (StripeException $e) {
			$error = $e->getMessage();
			$charge->addError('stripe', $error);

			return $this->listener->failed($this->_charge);
		}

		return $this->listener->succeeded($this->_charge);
	}


	/**
	 *
	 */
	private function createNew()
	{
		if (\Craft\craft()->elements->saveElement($this->_customer)) {
			$req                     = $this->buildStripeRequestWithDefaults();
			$stripeCharge            = \Market\market()['stripe']->customers()->create($req);
			$record                  = new ChargeRecord();
			$this->_charge->stripeId = $stripeCharge['id'];
			$record->id              = $this->_customer->id;
			$record->stripeId        = $this->_customer->stripeId;
			$record->amount          = $this->_customer->amount;
			//TODO: Set other attributes like customer/user etc
			$record->save();
		} else {
			// Fail loudly if we cannot make an element from this Model
			throw new \Market\Exception\BaseException("Could not save customer element");
		}
	}

	/**
	 * Builds a Stripe array for the create request
	 *
	 * @return array
	 */
	private function buildStripeRequestWithDefaults()
	{
		$card       = $this->_customer->card;
		$currency   = $this->_customer->currency;
		$amount     = $this->_customer->amount;
		$capture    = $this->_customer->capture;
		$chargeData = compact('card', 'currency', 'amount', 'capture');

		return $chargeData;
	}
}