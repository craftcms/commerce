<?php
namespace Stripey\Charge;

use Cartalyst\Stripe\Api\Exception\StripeException;
use Craft\Stripey_ChargeRecord as ChargeRecord;

class Creator
{
    /** @var $listener callback object */
    protected $listener;

    /** @var \CModel $_charge */
    private $_charge;

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
     * @throws \Stripey\Exception\BaseException
     */
    public function save(\CModel $charge)
    {
        $this->_charge = $charge;
        $isNewCharge   = !$charge->id;

        try {
            if ($isNewCharge) {
                $this->createNew();
            }else{

            }
        } catch (StripeException $e) {
            $error = $e->getMessage();
            $charge->addError('stripe', $error);

            return $this->listener->chargeFailed($this->_charge);
        }

        return $this->listener->chargeSucceeded($this->_charge);
    }


    /**
     *
     */
    private function createNew()
    {
        if (\Craft\craft()->elements->saveElement($this->_charge)) {
            $chargeRequest           = $this->buildChargeRequestWithDefaults();
            $stripeCharge            = \Stripey\stripey()['stripe']->charges()->create($chargeRequest);
            $this->_charge->stripeId = $stripeCharge['id'];
            $chargeRecord            = new ChargeRecord();
            $chargeRecord->id        = $this->_charge->id;
            $chargeRecord->stripeId  = $this->_charge->stripeId;
            $chargeRecord->amount    = $this->_charge->amount;
            //TODO: Set other attributes like customer/user etc
            $chargeRecord->save();
        } else {
            // Fail loudly if we cannot make an element from this Model
            throw new \Stripey\Exception\BaseException("Could not save element");
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
        $capture    = true;
        $chargeData = compact('card', 'currency', 'amount', 'capture');

        return $chargeData;
    }
}