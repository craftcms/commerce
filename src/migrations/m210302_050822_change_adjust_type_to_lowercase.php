<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

/**
 * m210302_050822_change_adjust_type_to_lowercase migration.
 */
class m210302_050822_change_adjust_type_to_lowercase extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->update('{{%commerce_orderadjustments}}', [
            'type' => 'shipping'
        ], [
            'type' => 'Shipping'
        ]);        
        
        $this->update('{{%commerce_orderadjustments}}', [
            'type' => 'discount'
        ], [
            'type' => 'Discount'
        ]);
        
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210302_050822_change_adjust_type_to_lowercase cannot be reverted.\n";
        return false;
    }
}
