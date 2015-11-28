<?php

namespace Craft;

use Commerce\Gateways\BaseGatewayAdapter;

/**
 * Gateways service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_GatewaysService extends BaseApplicationComponent
{
    /** @var BaseGatewayAdapter[] */
    private $gateways = [];

    public function __construct()
    {
        $this->_loadGateways();
    }

    /**
     * Pre-load all gateways
     */
    private function _loadGateways()
    {
        $allAdapters = [
            '\Commerce\Gateways\Omnipay\AuthorizeNet_AIM_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\AuthorizeNet_SIM_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Buckaroo_Ideal_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Buckaroo_PayPal_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\CardSave_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Coinbase_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Dummy_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Eway_Rapid_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\FirstData_Connect_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\GoCardless_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Manual_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Migs_ThreeParty_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Migs_TwoParty_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Mollie_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\MultiSafepay_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Netaxept_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\NetBanx_GatewayAdapter',
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
        ];

        $adapters = [];

        $licenseKeyStatus = craft()->plugins->getPluginLicenseKeyStatus('Commerce');
        $allowedStatuses = [LicenseKeyStatus::Valid, LicenseKeyStatus::Mismatched];

        if (in_array($licenseKeyStatus, $allowedStatuses)) {
            $adapters = craft()->plugins->call('commerce_registerGatewayAdapters');
            $adapters['Commerce'] = $allAdapters;
        } else {
            $adapters['Commerce'] = ['\Commerce\Gateways\Omnipay\Dummy_GatewayAdapter'];
        }

        foreach ($adapters as $adaptersByPlugin) {
            foreach ($adaptersByPlugin as $class) {
                $gateway = new $class;
                $this->gateways[$gateway->handle()] = $gateway;
            }
        }
    }

    /**
     * @return BaseGatewayAdapter[] indexed by handle
     */
    public function getAllGateways()
    {
        return $this->gateways;
    }
}
