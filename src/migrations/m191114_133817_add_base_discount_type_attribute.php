<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191114_133817_add_base_discount_type_attribute migration.
 */
class m191114_133817_add_base_discount_type_attribute extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $columnName = 'baseDiscountType';
        $values = ['value', 'percentTotal', 'percentTotalDiscounted', 'percentItems', 'percentItemsDiscounted'];

        $this->addColumn('{{%commerce_discounts}}', $columnName, $this->enum($columnName, $values)->notNull()->defaultValue('value'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191114_133817_add_base_discount_type_attribute cannot be reverted.\n";
        return false;
    }
}
