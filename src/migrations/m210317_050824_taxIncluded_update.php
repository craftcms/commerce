<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

/**
 * m210317_050824_taxIncluded_update migration.
 */
class m210317_050824_taxIncluded_update extends Migration
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
        echo "m210317_050824_taxIncluded_update cannot be reverted.\n";
        return false;
    }
}
