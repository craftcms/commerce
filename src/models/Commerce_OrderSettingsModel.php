<?php
namespace Craft;

/**
 * Order settings model.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property int $fieldLayoutId
 *
 * @property FieldLayoutRecord $fieldLayout
 *
 * @method null setFieldLayout(FieldLayoutModel $fieldLayout)
 * @method FieldLayoutModel getFieldLayout()
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_OrderSettingsModel extends BaseModel
{
    /**
     * @return null|string
     */
    function __toString()
    {
        return $this->handle;
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/ordersettings');
    }

    /**
     * @return array
     */
    public function behaviors()
    {
        return [
            'fieldLayout' => new FieldLayoutBehavior('Commerce_Order'),
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'name' => AttributeType::String,
            'handle' => AttributeType::String,
            'fieldLayoutId' => AttributeType::Number
        ];
    }
}