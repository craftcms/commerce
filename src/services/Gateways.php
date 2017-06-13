<?php

namespace craft\commerce\services;

use Commerce\Gateways\BaseGatewayAdapter;
use craft\commerce\gateways\Dummy_GatewayAdapter;
use craft\commerce\gateways\Manual_GatewayAdapter;
use craft\commerce\gateways\Paypal_Express_GatewayAdapter;
use craft\commerce\gateways\Paypal_Pro_GatewayAdapter;
use craft\commerce\gateways\Stripe_GatewayAdapter;
use craft\events\RegisterComponentTypesEvent;
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
    // Constants
    // =========================================================================

    /**
     * @event RegisterComponentTypesEvent The event that is triggered when registering additional gateway adapters.
     */
    const EVENT_REGISTER_GATEWAY_ADAPTERS = 'registerGatewayAdapters';

    // Properties
    // =========================================================================

    /** @var BaseGatewayAdapter[] */
    private $_gateways;

    /**
     * Returns all available gateways, indexed by handle.
     *
     * @return BaseGatewayAdapter[]
     */
    public function getAllGateways()
    {
        if (null === $this->_gateways) {
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
            Dummy_GatewayAdapter::class,
            Manual_GatewayAdapter::class,
            PayPal_Express_GatewayAdapter::class,
            PayPal_Pro_GatewayAdapter::class,
            Stripe_GatewayAdapter::class
        ];

        $event = new RegisterComponentTypesEvent([
            'types' => $classes
        ]);
        $this->trigger(self::EVENT_REGISTER_GATEWAY_ADAPTERS, $event);

        return $event->types;
    }
}
