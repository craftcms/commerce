<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Tax category record.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string $description
 * @property bool $default
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingCategory extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_shippingcategories}}';
    }
}
