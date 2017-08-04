<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Shipping rule category record.
 *
 * @property int              $id
 * @property bool             $enabled
 * @property string           $condition
 * @property float            $perItemRate
 * @property float            $weightRate
 * @property float            $percentageRate
 *
 * @property ShippingRule     $shippingRule
 * @property ShippingCategory $shippingCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.2
 */
class ShippingRuleCategory extends ActiveRecord
{
    const CONDITION_ALLOW = 'allow';
    const CONDITION_DISALLOW = 'disallow';
    const CONDITION_REQUIRE = 'require';

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_shippingrule_categories}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getShippingRule(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingRule::class, ['id' => 'shippingRuleId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getShippingCategory(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingCategory::class, ['id' => 'shippingCategoryId']);
    }
}