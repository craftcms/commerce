<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\base\StoreRecordTrait;
use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\db\SoftDeleteTrait;

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
    use StoreRecordTrait;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SHIPPINGCATEGORIES;
    }
}
