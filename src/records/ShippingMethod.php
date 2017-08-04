<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Shipping method record.
 *
 * @property int            $id
 * @property string         $name
 * @property string         $handle
 * @property bool           $enabled
 *
 * @property ShippingRule[] $rules
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class ShippingMethod extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_shippingmethods}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['name'], 'unique']
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getRules(): ActiveQueryInterface
    {
        return $this->hasMany(ShippingRule::class, ['shippingMethodId' => 'id']);
    }
}
