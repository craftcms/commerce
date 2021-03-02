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
        $adjustments =  (new Query())
            ->select([
                'id',
                'type'
            ])
            ->from(['{{%commerce_orderadjustments}}'])->all();

        $types = ['Shipping', 'Discount'];
        foreach ($adjustments as $adjustment) {
            $type = $adjustment['type'];

            if (in_array($type, $types)) {
                $this->update('{{%commerce_orderadjustments}}', [
                    'type' => strtolower($adjustment['type'])
                ], [
                    'id' => $adjustment['id']
                ]);
            }
        }
        
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
