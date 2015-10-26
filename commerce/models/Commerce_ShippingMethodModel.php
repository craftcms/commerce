<?php
namespace Craft;

use Commerce\Interfaces\ShippingMethod;
use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Shipping method model.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property bool $enabled
 * @property bool $default
 * @property Commerce_ShippingRuleModel[] $rules
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_ShippingMethodModel extends BaseModel implements ShippingMethod
{
    use Commerce_ModelRelationsTrait;

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
        return craft()->commerce_shippingRules->getAllByMethodId($this->id);
    }

    /**
     * @return bool
     */
    public function getIsEnabled()
    {
        return $this->enabled;
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
                'default' => 1
            ],
            'default' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 0
            ],
        ];
    }
}
