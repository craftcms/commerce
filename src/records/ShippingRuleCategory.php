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
 * @property ShippingRule     $shippingRule
 * @property ShippingCategory $shippingCategory
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class ShippingRuleCategory extends ActiveRecord
{
    // Constants
    // =========================================================================

    const CONDITION_ALLOW = 'allow';
    const CONDITION_DISALLOW = 'disallow';
    const CONDITION_REQUIRE = 'require';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
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
