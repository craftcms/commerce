<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;

/**
 * Order status record.
 *
 * @property string $color
 * @property bool $default
 * @property string $handle
 * @property int $id
 * @property bool $isArchived
 * @property bool $dateArchived
 * @property string $name
 * @property int $sortOrder
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItemStatus extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::LINEITEMSTATUSES;
    }
}
