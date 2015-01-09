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
        $charge->metadata    = craft()->request->getPost('metadata');
        //or TODO: make it possible to pass a customer with a default card on file
        //$charge->customer = 'cus_5GW06HEnx9t8pC';

        $chargeCreator = new \Stripey\Charge\Creator;
        $charge        = $chargeCreator->create($charge);

        if ($charge->hasErrors()) {
            craft()->urlManager->setRouteVariables(array(
                'charge' => $charge
            ));
        } else {
            $this->redirectToPostedUrl($charge);
        }
    }

    public function actionEditCharge(array $variables = array())
    {
        $chargeId = $variables['chargeId'];
        $this->renderTemplate('stripey/charges/_edit', compact('chargeId'));
    }

    public function actionRefundCharge(array $variables = array())
    {

    }
} 