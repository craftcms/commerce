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
 * Site Settings record.
 *
 * @property int $siteId
 * @property int $storeId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class SiteStore extends ActiveRecord
{
    use StoreRecordTrait;

    /**
     * @inheritDoc
     */
    public static function primaryKey(): array
    {
        return ['siteId'];
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SITESTORES;
    }
}
