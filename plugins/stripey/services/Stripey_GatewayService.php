<?php
namespace Craft;

use Omnipay\Common\GatewayFactory;
use Omnipay\Common\GatewayInterface;

class Stripey_GatewayService extends BaseApplicationComponent
{
    /** @var GatewayInterface[] */
    private $gateways;
    /** @var GatewayFactory */
    private $factory;

    public function __construct()
    {
        $this->_loadGateways();
    }

    /**
     * @return GatewayFactory
     */
    private function getFactory()
    {
        if(!$this->factory) {
            $this->factory = new GatewayFactory();
        }
        return $this->factory;
    }

    /**
     * @param string $shortName
     * @return GatewayInterface
     */
    public function getGateway($shortName)
    {
        return $this->getFactory()-> create($shortName);
    }

    /**
     * @return GatewayInterface[]
     */
    public function getGateways()
    {
        return $this->gateways;
    }

    /**
     * Pre-load all gateways
     */
    public function _loadGateways()
    {
        $gateways = array();

        $supportedGateways = $this->getFactory()->find();

        foreach ($supportedGateways as $shortName) {
            $gateways[] = $this->getGateway($shortName);
        }

        $this->gateways = $gateways;
    }
}