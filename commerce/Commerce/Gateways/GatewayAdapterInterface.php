<?php
namespace Commerce\Gateways;

use Craft\Commerce_PaymentMethodModel;
use Craft\BaseModel;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\AbstractRequest as OmnipayRequest;

/**
 * Interface GatewayAdapterInterface
 * @package Commerce\Gateways
 *
 * @method protected array defineAttributes() Use it to define setting parameters, it's labels and rules. Must be protected
 */
interface GatewayAdapterInterface
{
	/**
	 * @return Commerce_PaymentMethodModel|null
	 */
	public function getPaymentMethod();

	/**
	 * @param Commerce_PaymentMethodModel $paymentMethod
	 */
	public function setPaymentMethod(Commerce_PaymentMethodModel $paymentMethod);

    /** @return string */
    public function handle();

    /** @return string */
    public function displayName();

    /** @return string */
    public function getSettingsHtml();

	/**
	 * @param array $params
	 * @return string
	 */
	public function getPaymentFormHtml(array $params);

	/**
	 * @param CreditCard $card
	 * @param BaseModel  $paymentForm
	 *
	 * @return mixed
	 */
	public function populateCard(CreditCard $card, BaseModel $paymentForm);

	/**
	 * @param OmnipayRequest $request
	 * @param BaseModel      $paymentForm
	 *
	 * @return mixed
	 */
	public function populateRequest(OmnipayRequest $request, BaseModel $paymentForm);
}