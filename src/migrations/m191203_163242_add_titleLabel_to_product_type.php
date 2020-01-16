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
 * m191203_163242_add_titleLabel_to_product_type migration.
 */
class m191203_163242_add_titleLabel_to_product_type extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_producttypes}}', 'titleLabel', $this->string()->defaultValue('Title'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191203_163242_add_titleLabel_to_product_type cannot be reverted.\n";
        return false;
    }
}
