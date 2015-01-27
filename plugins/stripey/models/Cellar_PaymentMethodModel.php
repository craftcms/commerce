<?php
namespace Craft;


require_once(CRAFT_PLUGINS_PATH . "cellar/vendor/autoload.php");

use Omnipay\Common\GatewayFactory;

class Cellar_PaymentMethodModel extends BaseModel
{
    protected function defineAttributes()
    {
        return array(
            'class' => array(AttributeType::String, 'required' => true),
            'name' => array(AttributeType::String, 'required' => true),
            'settings' => AttributeType::Mixed,
            'enabled' => AttributeType::Bool
        );
    }

    public function getGateway()
    {
        if (!empty($this->class)) {
            return craft()->cellar_gateways->getGateway($this->class);
        }

        return null;
    }

    public function createGateway()
    {
        $gateway = GatewayFactory::create($this->class);
        $gateway->initialize($this->settings);

        return $gateway;
    }
}
