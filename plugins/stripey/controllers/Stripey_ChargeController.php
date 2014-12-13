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

        $defaultCurrency     = craft()->stripey_settings->getSettings()->defaultCurrency;
        $charge->amount      = craft()->request->getPost('amount', 100);
        $charge->currency    = craft()->request->getPost('currency', $defaultCurrency);
        $charge->card        = craft()->request->getPost('stripeToken');
        $charge->description = craft()->request->getPost('description');

        //or TODO: make it possible to pass a customer with a default card on file
        //$charge->customer = 'cus_5GW06HEnx9t8pC';

        $chargeCreator = new \Stripey\Charge\Creator($this);
        $chargeCreator->save($charge);
    }

    public function actionEditCharge(array $variables = array())
    {
        $chargeId = $variables['chargeId'];
        $this->renderTemplate('stripey/charges/_edit', compact('chargeId'));
    }

    public function actionRefundCharge(array $variables = array())
    {

    }




    public function chargeFailed(Stripey_ChargeModel $charge)
    {
        craft()->urlManager->setRouteVariables(array(
            'charge' => $charge
        ));
    }

    public function chargeSucceeded(Stripey_ChargeModel $charge)
    {
        $this->redirectToPostedUrl($charge);
    }
} 