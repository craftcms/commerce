<?php
namespace Stripey\Charge;

use Craft\Craft;
use Cartalyst\Stripe\Api\Exception\StripeException;
use Craft\Stripey;

class Creator
{
    protected $listener;

    function __construct($listener)
    {
        $this->listener = $listener;
    }

    public function create(\CModel $charge)
    {
        $isNewCharge = !$charge->id;

        if (!$isNewCharge) {
//            return $this->listener->updateCharge($charge);
        }

        $card       = $charge->card;
        $currency   = $charge->currency;
        $amount     = $charge->amount;
        $capture    = true;
        $chargeData = compact('card', 'currency', 'amount', 'capture');

        try {
            $newCharge        = Stripey::app()->api->stripe->charges()->create($chargeData);
//            $newCharge        = array("id"=>"sk_test_8Lvmi5qDkbHRLCsyexhvOGuj");
            $charge->stripeId = $newCharge['id'];
            $chargeRecord     = new \Craft\Stripey_ChargeRecord();

            if (Craft::app()->elements->saveElement($charge)) {
                if ($isNewCharge) {
                    $chargeRecord->id = $charge->id;
                }
            }

            $chargeRecord->stripeId = $charge->stripeId;
            $chargeRecord->amount = $charge->amount;

            //TODO: Check if user logged in and set to customer/user
            //$chargeRecord->userId = craft()->userSession->getUser()->id;
            $chargeRecord->save();

        } catch (StripeException $e) {
            $error = $e->getMessage();
            $charge->addError('carddsafdsa', $error);

            return false;
        }

        return true;
    }
}