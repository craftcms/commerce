<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\PaymentFormModels\StripePaymentFormModel;
use Craft\AttributeType;
use Craft\BaseModel;
use Omnipay\Common\CreditCard;

class Stripe_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
	public function handle()
	{
		return 'Stripe';
	}

	public function getPaymentFormModel()
	{
		return new StripePaymentFormModel();
	}

	public function getPaymentFormHtml(array $params)
	{
		$defaults = [
			'paymentMethod' => $this->getPaymentMethod(),
			'paymentForm'   => $this->getPaymentMethod()->getPaymentFormModel(),
			'adapter'       => $this
		];

		$params = array_merge($defaults, $params);

		\Craft\craft()->templates->includeJsFile('https://js.stripe.com/v2/');
		\Craft\craft()->templates->includeJsResource('lib/jquery.payment'.(\Craft\craft()->config->get('useCompressedJs') ? '.min' : '').'.js');

		return \Craft\craft()->templates->render('commerce/_gateways/_paymentforms/stripe', $params);

	}

	/**
	 * @param CreditCard $card
	 * @param BaseModel  $paymentForm
	 *
	 * @return void
	 */
	public function populateCard(CreditCard $card, BaseModel $paymentForm)
	{
		$card->setFirstName($paymentForm->firstName);
		$card->setLastName($paymentForm->lastName);
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
