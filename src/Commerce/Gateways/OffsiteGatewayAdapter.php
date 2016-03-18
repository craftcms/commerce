<?php
namespace Commerce\Gateways;

use Commerce\Gateways\PaymentFormModels\OffsitePaymentFormModel;
use Omnipay\Common\CreditCard;
use Craft\BaseModel;
use Omnipay\Common\Message\AbstractRequest as OmnipayRequest;

/**
 * Class OffsiteGatewayAdapter
 *
 * @package Commerce\Gateways
 *
 */
abstract class OffsiteGatewayAdapter extends BaseGatewayAdapter
{
	/**
	 * @return bool
	 */
	public function requiresCreditCard()
	{
		return false;
	}

	public function cpPaymentsEnabled()
	{
		return true;
	}

	/**
	 * @return \Commerce\Gateways\PaymentFormModels\BasePaymentFormModel
	 */
	public function getPaymentFormModel()
	{
		return new OffsitePaymentFormModel();
	}

	/**
	 * @param array $params
	 *
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

		return \Craft\craft()->templates->render('commerce/_gateways/_paymentforms/offsite', $params);
	}

	/**
	 * @param CreditCard $card
	 * @param BaseModel  $paymentForm
	 *
	 * @return void
	 */
	public function populateCard(CreditCard $card, BaseModel $paymentForm)
	{
	}

	/**
	 * @param OmnipayRequest $card
	 * @param BaseModel      $paymentForm
	 *
	 * @return void
	 */
	public function populateRequest(OmnipayRequest $card, BaseModel $paymentForm)
	{
	}
}
