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

/**
 * Tax zone record.
 *
 * @property bool $default
 * @property int $id
 * @property string $name
 * @property string $description
 * @property array $condition
 * @property int $storeId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxZone extends ActiveRecord
{
    use StoreRecordTrait;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::TAXZONES;
    }
}
