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
 * m180620_161904_fix_primaryAddressCascade migration.
 */
class m180620_161904_fix_primaryAddressCascade extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        MigrationHelper::dropAllForeignKeysOnTable('{{%commerce_customers}}');

        $this->addForeignKey(null, '{{%commerce_customers}}', ['userId'], '{{%users}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_customers}}', ['primaryBillingAddressId'], '{{%commerce_addresses}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%commerce_customers}}', ['primaryShippingAddressId'], '{{%commerce_addresses}}', ['id'], 'SET NULL');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180620_161904_fix_primaryAddressCascade cannot be reverted.\n";
        return false;
    }
}