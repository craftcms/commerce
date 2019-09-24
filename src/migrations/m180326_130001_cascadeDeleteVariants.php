<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m180326_130001_cascadeDeleteVariants migration.
 */
class m180326_130001_cascadeDeleteVariants extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->delete('{{%commerce_variants}}', ['productId' => null]);
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_variants}}');

        // Now we can set the productId column to NOT NULL
        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_variants}} alter column [[productId]] set not null');
        } else {
            $this->alterColumn('{{%commerce_variants}}', 'productId', $this->integer()->notNull());
        }

        $this->addForeignKey(null, '{{%commerce_variants}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%commerce_variants}}', ['productId'], '{{%commerce_products}}', ['id'], 'CASCADE', 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180326_130001_cascadeDeleteVariants cannot be reverted.\n";
        return false;
    }
}
