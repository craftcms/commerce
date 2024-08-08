<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m240808_090256_cascade_delete_variants_on_product_delete migration.
 */
class m240808_090256_cascade_delete_variants_on_product_delete extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {

        // Take the opportunity to clean up any orphaned variants
        $allVariantsWithNullOwner = (new Query())
            ->select('id')
            ->from('{{%commerce_variants}}')
            ->where(['primaryOwnerId' => null]);
        $this->delete('{{%elements}}', ['id' => $allVariantsWithNullOwner]);

        // Should cascade delete variants when a product is deleted
        $this->dropForeignKeyIfExists('{{%commerce_variants}}', ['primaryOwnerId']);
        $this->addForeignKey(null, '{{%commerce_variants}}', ['primaryOwnerId'], '{{%commerce_products}}', ['id'], 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240808_090256_cascade_delete_variants_on_product_delete cannot be reverted.\n";
        return false;
    }
}
