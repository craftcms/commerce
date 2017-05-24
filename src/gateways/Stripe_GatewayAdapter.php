<?php

namespace craft\commerce\gateways;

use Craft;
use craft\commerce\gateway\models\StripePaymentFormModel;

class Stripe_GatewayAdapter extends CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'Stripe';
    }

    public function getPaymentFormModel()
    {
        return new StripePaymentFormModel();
    }

    public function cpPaymentsEnabled()
    {
        return true;
    }

    public function getPaymentFormHtml(array $params)
    {
        $defaults = [
            'paymentMethod' => $this->getPaymentMethod(),
            'paymentForm' => $this->getPaymentMethod()->getPaymentFormModel(),
            'adapter' => $this
        ];

        $params = array_merge($defaults, $params);

        Craft::$app->getView()->includeJsFile('https://js.stripe.com/v2/');
        Craft::$app->getView()->includeJsResource('lib/jquery.payment'.(Craft::$app->getConfig()->getGeneral('useCompressedJs') ? '.min' : '').'.js');

        return Craft::$app->getView()->render('commerce/_gateways/_paymentforms/stripe', $params);
    }

    public function defineAttributes()
    {
        // In addition to the standard gateway config, here is some custom config that is useful.
        $attr = parent::defineAttributes();
        $attr['publishableKey'] = [AttributeType::String];
        $attr['publishableKey']['label'] = $this->generateAttributeLabel('publishableKey');

        return $attr;
    }

}
