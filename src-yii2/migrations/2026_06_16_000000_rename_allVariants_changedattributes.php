<?php

use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use yii\db\Expression;

/**
 * Between Commerce 5.2 and 5.5.2, the NestedElementManager for variants used 'allVariants'
 * as the attribute name instead of 'variants'. This caused 'allVariants' to be written into
 * the {{%changedattributes}} table. After upgrading, Craft's mergeCanonicalChanges() would
 * try to access $product->allVariants (which no longer exists), throwing an UnknownPropertyException
 * when opening a product with a provisional draft.
 * This migration renames any lingering 'allVariants' entries to 'variants' for Product elements.
 */
return new class extends Migration {
    public function safeUp(): bool
    {
        $productSubquery = (new Query())
            ->select(['id'])
            ->from([Table::ELEMENTS])
            ->where(['type' => 'craft\commerce\elements\Product']);

        // Insert 'variants' rows for Products that have 'allVariants' but no existing 'variants' entry
        $select = (new Query())
            ->select(['ca.elementId', 'ca.siteId', new Expression("'variants'"), 'ca.dateUpdated', 'ca.propagated', 'ca.userId'])
            ->from(['ca' => '{{%changedattributes}}'])
            ->where(['ca.attribute' => 'allVariants', 'ca.elementId' => $productSubquery])
            ->andWhere('NOT EXISTS (SELECT 1 FROM {{%changedattributes}} [[ca2]] WHERE [[ca2.elementId]] = [[ca.elementId]] AND [[ca2.siteId]] = [[ca.siteId]] AND [[ca2.attribute]] = \'variants\')');

        [$sql, $params] = $this->db->getQueryBuilder()->build($select);
        $table = $this->db->quoteTableName('{{%changedattributes}}');
        $this->db->createCommand(
            "INSERT INTO $table ([[elementId]], [[siteId]], [[attribute]], [[dateUpdated]], [[propagated]], [[userId]]) $sql",
            $params
        )->execute();

        // Delete all 'allVariants' rows for Products
        $this->delete('{{%changedattributes}}', [
            'attribute' => 'allVariants',
            'elementId' => $productSubquery,
        ]);

        return true;
    }

    public function safeDown(): bool
    {
        echo "2026_06_16_000000_rename_allVariants_changedattributes cannot be reverted.\n";
        return false;
    }
};
