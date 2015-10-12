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
        $adapters = craft()->plugins->call('registerCommerceGatewayAdapters');

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
    public function getAll()
    {
        return $this->gateways;
    }
}
