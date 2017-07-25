<?php

namespace craft\commerce\gateways;

use craft\base\SavableComponentInterface;
use craft\commerce\models\BasePaymentMethod;

/**
 * Interface GatewayAdapterInterface
 *
 * @package Commerce\Gateways
 */
interface GatewayAdapterInterface extends SavableComponentInterface
{
    /**
     * @return BasePaymentMethod|null
     */
    public function getPaymentMethod();

    /**
     * @param BasePaymentMethod $paymentMethod
     */
    public function setPaymentMethod(BasePaymentMethod $paymentMethod);

    /**
     * @return string
     */
    public function handle();

    /**
     * @return string
     */
    public function getSettingsHtml();
}