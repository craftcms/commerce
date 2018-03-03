<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Tax category record.
 *
 * @property bool $default
 * @property string $description
 * @property string $handle
 * @property int $id
 * @property string $name
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxCategory extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_taxcategories}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['handle'], 'required']
        ];
    }
}
