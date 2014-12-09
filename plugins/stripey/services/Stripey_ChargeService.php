<?php
namespace Craft;

use Cartalyst\Stripe\Api\Exception\StripeException;


/**
 * Class Stripey_ChargeService
 *
 * @package Craft
 */
class Stripey_ChargeService extends BaseApplicationComponent
{

    /**
     * @param Stripey_ChargeModel $charge
     */
    public function updateCharge(Stripey_ChargeModel $charge)
    {
        $id          = $charge->stripeId;
        $description = $charge->description;
        $chargeData  = compact('id', 'description');
        try {
            stripey()->api->stripe->charges()->update($chargeData);
        } catch (StripeException $e) {
            $error = $e->getMessage();
            $charge->addError('card', $error);

            return false;
        }

        return true;
    }


}