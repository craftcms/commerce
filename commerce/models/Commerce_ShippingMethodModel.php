<?php
namespace Craft;

use Commerce\Interfaces\ShippingMethod;

/**
 * Shipping method model.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property bool $enabled
 * @property Commerce_ShippingRuleModel[] $rules
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_ShippingMethodModel extends BaseModel implements ShippingMethod
{
    /**
     * @return string
     */
    public function getType()
    {
        return Craft::t('Custom');
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->getAttribute('id');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getAttribute('name');
    }

    /**
     * @return string
     */
    public function getHandle()
    {
        return $this->getAttribute('handle');
    }

    /**
     * @return Commerce_ShippingRuleModel[]
     */
    public function getRules()
    {
        return craft()->commerce_shippingRules->getAllShippingRulesByShippingMethodId($this->id);
    }

    /**
     * @return bool
     */
    public function getIsEnabled()
    {
        return $this->getAttribute('enabled');
    }

    /**
     * Not applicable since we link to our own.
     *
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/shippingmethods/'.$this->id);
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'name' => [AttributeType::String, 'required' => true],
            'handle' => [AttributeType::Handle, 'required' => true],
            'enabled' => [
                AttributeType::Bool,
                'required' => true,
                'default' => true
            ]
        ];
    }
}
