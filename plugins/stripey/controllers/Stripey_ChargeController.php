<?php
namespace Craft;

/**
 * Class Stripey_ChargeController
 *
 * @package Craft
 */

class Stripey_ChargeController extends Stripey_BaseController
{

    protected $allowAnonymous = array('actionNewCharge');

    /**
     * The public Charge creation action.
     */
    public function actionNewCharge()
    {
        $this->requirePostRequest();
        $charge = new Stripey_ChargeModel();

        // Required charge params
        $charge->amount   = craft()->request->getPost('amount', 100);
        $defaultCurrency  = stripey()->settings->getSettings()->defaultCurrency;
        $charge->currency = craft()->request->getPost('currency', $defaultCurrency);

        $charge->card     = craft()->request->getPost('stripeToken');

        //or TODO: make it possible to pass a customer with a default card on file
        //$charge->customer = 'cus_5GW06HEnx9t8pC';

        $charge->description = craft()->request->getPost('description');
        //TODO: implement metadata on charge

        $chareCreator = new \Stripey\Charge\Creator($this);
        if($chareCreator->create($charge)) {
            $this->redirectToPostedUrl($charge);
        }else{
            craft()->urlManager->setRouteVariables(array(
                'charge' => $charge
            ));
        }
    }
} 