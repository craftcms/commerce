<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Shipping method record.
 *
 * @property bool $enabled
 * @property string $handle
 * @property int $id
 * @property string $name
 * @property ShippingRule[] $rules
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingMethod extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SHIPPINGMETHODS;
    }

    public function getRules(): ActiveQueryInterface
    {
        return $this->hasMany(ShippingRule::class, ['shippingMethodId' => 'id']);
    }
}
