<?php
namespace Commerce\Gateways;

use Commerce\Gateways;
use Craft\BaseModel;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\AbstractRequest as OmnipayRequest;
use Commerce\Gateways\PaymentFormModels\CreditCardPaymentFormModel;

/**
 * Class CreditCardGatewayAdapter
 *
 * @package Commerce\Gateways
 *
 */
abstract class CreditCardGatewayAdapter extends BaseGatewayAdapter
{

	public function getPaymentFormModel()
	{
		return new CreditCardPaymentFormModel();
	}

	/**
	 * @return string
	 */
	public function getPaymentFormHtml(array $params)
	{
		$defaults = [
			'paymentMethod' => $this->getPaymentMethod(),
			'paymentForm'   => $this->getPaymentMethod()->getPaymentFormModel(),
			'adapter'       => $this
		];

		$params = array_merge($defaults, $params);

		return \Craft\craft()->templates->render('commerce/_gateways/_paymentforms/creditcard', $params);
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
		$card->setNumber($paymentForm->number);
		$card->setExpiryMonth($paymentForm->month);
		$card->setExpiryYear($paymentForm->year);
		$card->setCvv($paymentForm->cvv);
	}

	/**
	 * @param OmnipayRequest $request
	 * @param BaseModel      $paymentForm
	 *
	 * @return void
	 */
	public function populateRequest(OmnipayRequest $request, BaseModel $paymentForm)
	{
		$request->setToken($paymentForm->token);
	}
}