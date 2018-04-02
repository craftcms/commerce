<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180401_150701_primary_addresses migration.
 */
class m180401_150701_primary_addresses extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->renameColumn('{{%commerce_customers}}', 'lastUsedBillingAddressId', 'primaryBillingAddressId');
        $this->renameColumn('{{%commerce_customers}}', 'lastUsedShippingAddressId', 'primaryShippingAddressId');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180401_150701_primary_addresses cannot be reverted.\n";
        return false;
    }
}
