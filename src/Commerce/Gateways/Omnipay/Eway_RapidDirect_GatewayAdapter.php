<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\PaymentFormModels\CreditCardPaymentFormModel;
use Commerce\Gateways\PaymentFormModels\EwayRapidDirectPaymentFormModel;
use Craft\AttributeType;
use Craft\BaseModel;
use Omnipay\Common\Message\AbstractRequest as OmnipayRequest;

class Eway_RapidDirect_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
	public function handle()
	{
		return 'Eway_RapidDirect';
	}

	public function cpPaymentsEnabled()
	{
		return true;
	}

	public function getPaymentFormModel()
	{
		$csekey = isset($this->getPaymentMethod()->settings['CSEKey']) && $this->getPaymentMethod()->settings['CSEKey'];
		if ($csekey)
		{
			return new EwayRapidDirectPaymentFormModel();
		}
		else
		{
			return new CreditCardPaymentFormModel();
		}
	}

	public function getPaymentFormHtml(array $params)
	{
		$defaults = [
			'paymentMethod' => $this->getPaymentMethod(),
			'paymentForm'   => $this->getPaymentMethod()->getPaymentFormModel(),
			'adapter'       => $this
		];

		$params = array_merge($defaults, $params);

		$csekey = isset($this->getPaymentMethod()->settings['CSEKey']) && $this->getPaymentMethod()->settings['CSEKey'];
		if ($csekey)
		{
			if (\Craft\craft()->config->get('devMode'))
			{
				\Craft\craft()->templates->includeJsFile('https://secure.ewaypayments.com/scripts/eCrypt.debug.js');
			}
			else
			{
				\Craft\craft()->templates->includeJsFile('https://secure.ewaypayments.com/scripts/eCrypt.js');
			}
		}

		\Craft\craft()->templates->includeJsResource('lib/jquery.payment'.(\Craft\craft()->config->get('useCompressedJs') ? '.min' : '').'.js');

		if ($csekey)
		{
			return \Craft\craft()->templates->render('commerce/_gateways/_paymentforms/ewayrapiddirectencrypt', $params);
		}
		else
		{
			return \Craft\craft()->templates->render('commerce/_gateways/_paymentforms/creditcard', $params);
		}
	}

	public function defineAttributes()
	{
		// In addition to the standard gateway config, here is some custom config that is useful.
		$attr = parent::defineAttributes();
		$attr['CSEKey'] = [AttributeType::String];
		$attr['CSEKey']['label'] = $this->generateAttributeLabel('CSEKey');

		return $attr;
	}

	public function populateRequest(OmnipayRequest $request, BaseModel $paymentForm)
	{
		$csekey = isset($this->getPaymentMethod()->settings['CSEKey']) && $this->getPaymentMethod()->settings['CSEKey'];
		if ($csekey)
		{
			$request->setEncryptedCardNumber($paymentForm->encryptedCardNumber);
			$request->setEncryptedCardCvv($paymentForm->encryptedCardCvv);
		}
	}
}
