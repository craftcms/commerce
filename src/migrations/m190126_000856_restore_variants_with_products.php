<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m190126_000856_restore_variants_with_products migration.
 */
class m190126_000856_restore_variants_with_products extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_variants}}', 'deletedWithProduct', $this->boolean()->null()->after('maxQty'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190126_000856_restore_variants_with_products cannot be reverted.\n";
        return false;
    }
}
