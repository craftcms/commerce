<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Stripe_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Stripe';
    }

    public function getPaymentFormHtml()
    {
        $html = \Craft\craft()->templates->render('commerce/_gateways/_paymentforms/stripe', [
            'adapter' => $this,
        ]);

        return $html;
    }
}