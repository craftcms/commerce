<?php

namespace Craft;

use Commerce\Gateways\BaseGatewayAdapter;

/**
 * Gateways service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_GatewaysService extends BaseApplicationComponent
{
    /** @var BaseGatewayAdapter[] */
    private $_gateways;

    /**
     * Returns all available gateways, indexed by handle.
     *
     * @return BaseGatewayAdapter[]
     */
    public function getAllGateways()
    {
        if (!isset($this->_gateways)) {
            $this->_gateways = $this->_getGateways();
        }

        return $this->_gateways;
    }

    /**
     * Returns all available gateways, indexed by handle.
     *
     * @return BaseGatewayAdapter[]
     */
    private function _getGateways()
    {
        $classes = $this->_getGatewayClasses();
        $gateways = [];
        $names = [];

        foreach ($classes as $class) {
            $gateway = new $class;
            $gateways[$gateway->handle()] = $gateway;
            $names[] = $gateway->displayName();
        }

        // Sort alphabetically
        array_multisort($names, SORT_NATURAL | SORT_FLAG_CASE, $gateways);

        return $gateways;
    }

    /**
     * Returns the full list of available gateway classes
     *
     * @return string[]
     */
    private function _getGatewayClasses()
    {
        $classes = [
            '\Commerce\Gateways\Omnipay\AuthorizeNet_AIM_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\AuthorizeNet_SIM_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Buckaroo_Ideal_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Buckaroo_PayPal_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Buckaroo_CreditCard_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\CardSave_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Coinbase_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Dummy_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Eway_Rapid_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Eway_RapidDirect_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Eway_Direct_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Eway_RapidShared_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\FirstData_Connect_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\FirstData_Payeezy_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\GoCardless_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Manual_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Migs_ThreeParty_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Migs_TwoParty_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Mollie_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\MultiSafepay_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\MultiSafepay_RestGatewayAdapter',
            '\Commerce\Gateways\Omnipay\Netaxept_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\NetBanx_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\NetBanx_Hosted_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\PayFast_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Payflow_Pro_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\PaymentExpress_PxPay_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\PaymentExpress_PxPost_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\PayPal_Express_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\PayPal_Pro_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Pin_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\SagePay_Direct_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\SagePay_Server_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\SecurePay_DirectPost_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Stripe_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\TargetPay_Directebanking_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\TargetPay_Ideal_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\TargetPay_Mrcash_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\TwoCheckout_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\WorldPay_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\WorldPay_Json_GatewayAdapter',
        ];

        // Let plugins register more gateway adapters classes
        $allPluginClasses = craft()->plugins->call('commerce_registerGatewayAdapters', [], true);

        foreach ($allPluginClasses as $pluginClasses) {
            $classes = array_merge($classes, $pluginClasses);
        }

        return $classes;
    }
}
