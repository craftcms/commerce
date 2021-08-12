<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m210317_093136_includedTax_fix migration.
 */
class m210317_093136_includedTax_fix extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->update('{{%commerce_orderadjustments}}', [
            'type' => 'tax',
            'included' => true
        ], [
            'type' => 'taxIncluded'
        ]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210317_093136_includedTax_fix cannot be reverted.\n";
        return false;
    }
}