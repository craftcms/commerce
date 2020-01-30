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
 * m190301_161406_unique_sku_constraint_in_app migration.
 */
class m190301_161406_unique_sku_constraint_in_app extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        MigrationHelper::dropIndexIfExists('{{%commerce_purchasables}}', ['sku'], true, $this);
        MigrationHelper::dropIndexIfExists('{{%commerce_variants}}', ['sku'], true, $this);

        $this->createIndex(null, '{{%commerce_purchasables}}', 'sku', false); // Application layer now enforces unique
        $this->createIndex(null, '{{%commerce_variants}}', 'sku', false); // Application layer now enforces unique
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190301_161406_unique_sku_constraint_in_app cannot be reverted.\n";

        return false;
    }
}
