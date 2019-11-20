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
 * Order adjustment record.
 *
 * @property float $amount
 * @property string $description
 * @property int $id
 * @property bool $included
 * @property int|null $lineItemId
 * @property string $name
 * @property Order $order
 * @property int $orderId
 * @property string $sourceSnapshot
 * @property string $type
 * @property bool $isEstimated
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderAdjustment extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::ORDERADJUSTMENTS;
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getOrder(): ActiveQueryInterface
    {
        return $this->hasOne(Order::class, ['id' => 'orderId']);
    }
}
