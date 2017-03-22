<?php

namespace craft\commerce\services;

use Commerce\Gateways\BaseGatewayAdapter;
use yii\base\Component;

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
class Gateways extends Component
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
            '\Commerce\Gateways\Omnipay\Dummy_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Manual_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\PayPal_Express_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\PayPal_Pro_GatewayAdapter',
            '\Commerce\Gateways\Omnipay\Stripe_GatewayAdapter',
        ];

        // Let plugins register more gateway adapters classes
        $allPluginClasses = craft()->plugins->call('commerce_registerGatewayAdapters', [], true);

        foreach ($allPluginClasses as $pluginClasses) {
            $classes = array_merge($classes, $pluginClasses);
        }

        return $classes;
    }
}
