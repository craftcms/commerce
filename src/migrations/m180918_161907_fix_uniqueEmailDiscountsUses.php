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
 * m180918_161907_fix_uniqueEmailDiscountsUses migration.
 */
class m180918_161907_fix_uniqueEmailDiscountsUses extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Drop FKs first
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_email_discountuses}}', $this);
        // Drop all indexes
        MigrationHelper::dropAllIndexesOnTable('{{%commerce_email_discountuses}}', $this);
        // Rebuild indexes
        $this->createIndex(null, '{{%commerce_email_discountuses}}', ['email', 'discountId'], true);
        $this->createIndex(null, '{{%commerce_email_discountuses}}', ['discountId'], false);
        // Rebuild FKs
        $this->addForeignKey(null, '{{%commerce_email_discountuses}}', ['discountId'], '{{%commerce_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180918_161907_fix_uniqueEmailDiscountsUses cannot be reverted.\n";
        return false;
    }
}
