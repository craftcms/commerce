<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\db\SoftDeleteTrait;
use yii\db\ActiveQueryInterface;

/**
 * Tax category record.
 *
 * @property bool $default
 * @property string $description
 * @property string $handle
 * @property int $id
 * @property int $storeId
 * @property string $name
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingCategory extends ActiveRecord
{
    use SoftDeleteTrait;
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SHIPPINGCATEGORIES;
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getStore(): ActiveQueryInterface
    {
        return $this->hasOne(Store::class, ['id' => 'storeId']);
    }
}
