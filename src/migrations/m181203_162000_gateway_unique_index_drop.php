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
 * m181203_162000_gateway_unique_index_drop migration.
 */
class m181203_162000_gateway_unique_index_drop extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Drop the unique constraints
        MigrationHelper::dropAllIndexesOnTable('{{%commerce_gateways}}', $this);

        $this->createIndex(null, '{{%commerce_gateways}}', 'handle', false);
        $this->createIndex(null, '{{%commerce_gateways}}', 'isArchived', false);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181203_162000_gateway_unique_index_drop cannot be reverted.\n";
        return false;
    }
}
