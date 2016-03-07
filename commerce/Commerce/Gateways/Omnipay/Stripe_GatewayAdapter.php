<?php
namespace Commerce\Gateways\Omnipay;

use Craft\AttributeType;

class Stripe_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
	public function handle()
	{
		return 'Stripe';
	}

	public function getPaymentFormHtml(array $params)
	{
		$defaults = [
			'paymentMethod' => $this->getPaymentMethod(),
			'paymentForm'   => $this->getPaymentMethod()->getPaymentFormModel(),
			'adapter'       => $this
		];

		$params = array_merge($defaults, $params);

		\Craft\craft()->templates->includeJsResource('commerce/js/_gateways/stripe.js');
		return \Craft\craft()->templates->render('commerce/_gateways/_paymentforms/stripe', $params);

	}

	public function defineAttributes()
	{
		$attr = parent::defineAttributes();
		$attr['publishableKey'] = [AttributeType::String];
		$attr['publishableKey']['label'] = $this->generateAttributeLabel('publishableKey');

		return $attr;
	}
}