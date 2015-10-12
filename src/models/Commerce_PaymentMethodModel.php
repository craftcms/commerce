<?php
namespace Craft;

use Commerce\Gateways\BaseGatewayAdapter;

/**
 * Class Commerce_PaymentMethodModel
 *
 * @package Craft
 *
 * @property int $id
 * @property string $class
 * @property string $name
 * @property array $settings
 * @property bool $cpEnabled
 * @property bool $frontendEnabled
 *
 * @property BaseGatewayAdapter $gateway
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_PaymentMethodModel extends BaseModel
{
    /** @var BaseGatewayAdapter */
    private $_gatewayAdapter;

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/paymentmethods/' . $this->id);
    }

    /**
     * Whether this payment method requires credit card details
     * @return bool
     */
    public function requiresCard()
    {
        return $this->getGatewayAdapter()->requiresCreditCard();
    }

    /**
     * @return BaseGatewayAdapter
     */
    public function getGatewayAdapter()
    {
        if (!empty($this->class) && !$this->_gatewayAdapter) {
            $this->_gatewayAdapter = craft()->commerce_gateway->getAll()[$this->class];
            $this->_gatewayAdapter->setAttributes($this->settings);
        }

        return $this->_gatewayAdapter;
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'class' => AttributeType::String,
            'name' => AttributeType::String,
            'cpEnabled' => AttributeType::Bool,
            'frontendEnabled' => AttributeType::Bool,
            'settings' => [AttributeType::Mixed],
        ];
    }
}
