<?php
namespace Craft;

require_once(CRAFT_PLUGINS_PATH . "cellar/vendor/autoload.php");

use Omnipay\Common\GatewayFactory;

class Cellar_GatewaysService extends BaseApplicationComponent
{
    private $gateways;

    public function __construct()
    {
        $this->_loadGateways();
    }

    public function getGateway($shortName)
    {
        return GatewayFactory::create($shortName);
    }

    public function getGateways()
    {
        return $this->gateways;
    }

    public function _loadGateways()
    {
        $gateways = array();

        $supportedGateways = GatewayFactory::find();

        foreach ($supportedGateways as $gatewayShortName) {

            $gateway = $this->getGateway($gatewayShortName);

            array_push($gateways, $gateway);
        }

        $this->gateways = $gateways;
    }
}