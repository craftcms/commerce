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
 * @property string $paymentType
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
     * Populates a new model instance with a given set of attributes.
     *
     * @param mixed $values
     *
     * @return BaseModel
     */
    public static function populateModel($values)
    {
        $paymentMethod = parent::populateModel($values);

        if ($paymentMethod->id) {
            // Are its settings being set from the config file?
            $paymentMethodSettings = craft()->config->get('paymentMethodSettings', 'commerce');

            if (isset($paymentMethodSettings[$paymentMethod->id])) {
                $paymentMethod->settings = array_merge($paymentMethod->settings, $paymentMethodSettings[$paymentMethod->id]);
            }
        }

        return $paymentMethod;
    }

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
        $gateways = craft()->commerce_gateways->getAllGateways();
        if (!empty($this->class) && !$this->_gatewayAdapter) {
            $this->_gatewayAdapter = $gateways[$this->class];
            $this->_gatewayAdapter->setAttributes($this->settings);
        }

        return $this->_gatewayAdapter;
    }

    /**
     * @return array
     */
    public function getPaymentTypeOptions()
    {
        return [
            'authorize' => Craft::t('Authorize Only (Manually Capture)'),
            'purchase' => Craft::t('Purchase (Authorize and Capture Immediately)'),
        ];
    }

    /**
     * Whether this payment method supports credit card details
     * @return bool
     */
    public function supportsCard()
    {
        return $this->getGatewayAdapter()->requiresCreditCard();
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
            'paymentType' => [
                AttributeType::Enum,
                'values' => ['authorize', 'purchase'],
                'required' => true,
                'default' => 'purchase'
            ],
            'frontendEnabled' => AttributeType::Bool,
            'settings' => [AttributeType::Mixed],
        ];
    }
}
