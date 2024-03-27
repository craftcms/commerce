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
use DateTime;

/**
 * Order status record.
 *
 * @property string $color
 * @property bool $default
 * @property string $handle
 * @property int $id
 * @property bool $isArchived
 * @property string|DateTime|null $dateArchived
 * @property string $name
 * @property int $sortOrder
 * @property int $storeId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItemStatus extends ActiveRecord
{
    use StoreRecordTrait;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::LINEITEMSTATUSES;
    }
}
