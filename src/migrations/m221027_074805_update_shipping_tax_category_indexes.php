<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m221027_074805_update_shipping_tax_category_indexes migration.
 */
class m221027_074805_update_shipping_tax_category_indexes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropIndexIfExists('{{%commerce_taxcategories}}', 'handle', true);
        $this->dropIndexIfExists('{{%commerce_shippingcategories}}', 'handle', true);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221027_074805_update_shipping_tax_category_indexes cannot be reverted.\n";
        return false;
    }
}
