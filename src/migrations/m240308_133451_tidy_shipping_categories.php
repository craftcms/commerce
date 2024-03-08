<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m240308_133451_tidy_shipping_categories migration.
 */
class m240308_133451_tidy_shipping_categories extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $storeIds = (new Query())
            ->select(['id'])
            ->from(Table::STORES)
            ->column();

        $this->delete(Table::SHIPPINGCATEGORIES, ['not', ['storeId' => $storeIds]]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240308_133451_tidy_shipping_categories cannot be reverted.\n";
        return false;
    }
}
