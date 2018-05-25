<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180525_161904_available_for_purchase migration.
 */
class m180525_161904_available_for_purchase extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_products}}', 'availableForPurchase', $this->boolean());
        $this->update('{{%commerce_products}}', ['availableForPurchase' => true]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180525_161904_available_for_purchase cannot be reverted.\n";
        return false;
    }
}