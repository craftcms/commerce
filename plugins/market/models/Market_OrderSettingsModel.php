<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_OrderSettingsModel
 *
 * @property int                         $id
 * @property string                      $name
 * @property string                      $handle
 * @property int                         $fieldLayoutId
 *
 * @property FieldLayoutRecord           fieldLayout
 *
 * @method null setFieldLayout(FieldLayoutModel $fieldLayout)
 * @method FieldLayoutModel getFieldLayout()
 * @package Craft
 */
class Market_OrderSettingsModel extends BaseModel
{
    use Market_ModelRelationsTrait;

    function __toString()
    {
        return Craft::t($this->handle);
    }

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('market/settings/ordersettings');
    }

    public function behaviors()
    {
        return [
            'fieldLayout' => new FieldLayoutBehavior('Market_Order'),
        ];
    }

    protected function defineAttributes()
    {
        return [
            'id'               => AttributeType::Number,
            'name'             => AttributeType::String,
            'handle'           => AttributeType::String,
            'fieldLayoutId'    => AttributeType::Number
        ];
    }

}