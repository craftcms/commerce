<?php
namespace Commerce\Gateways;

use craft\commerce\models\PaymentMethod;

/**
 * Interface GatewayAdapterInterface
 *
 * @package Commerce\Gateways
 *
 * @method protected array defineAttributes() Use it to define setting parameters, it's labels and rules. Must be protected
 */
interface GatewayAdapterInterface
{
    /**
     * @return PaymentMethod|null
     */
    public function getPaymentMethod();

    /**
     * @param PaymentMethod $paymentMethod
     */
    public function setPaymentMethod(PaymentMethod $paymentMethod);

    /** @return string */
    public function handle();

    /** @return string */
    public function displayName();

    /** @return string */
    public function getSettingsHtml();
}