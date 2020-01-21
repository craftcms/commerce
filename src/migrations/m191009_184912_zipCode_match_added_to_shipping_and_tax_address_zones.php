<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m191009_184912_zipCode_match_added_to_shipping_and_tax_address_zones migration.
 */
class m191009_184912_zipCode_match_added_to_shipping_and_tax_address_zones extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_shippingzones}}', 'zipCodeConditionFormula', $this->string());
        $this->addColumn('{{%commerce_taxzones}}', 'zipCodeConditionFormula', $this->string());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191009_184912_zipCode_match_added_to_shipping_and_tax_address_zones cannot be reverted.\n";
        return false;
    }
}
