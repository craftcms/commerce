<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m200617_172414_fix_country_state_sort_orders migration.
 */
class m200617_172414_fix_country_state_sort_orders extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $countries = (new Query())
            ->select(['*'])
            ->from('{{%commerce_countries}}')
            ->limit(null)
            ->orderBy(['name' => SORT_ASC])
            ->where(['sortOrder' => null])
            ->all();

        $sortNumber = 1000; //start at 1000 so it doesn't affect if any countries had a sort previous
        foreach ($countries as $country) {
            $this->update('{{%commerce_countries}}', ['sortOrder' => $sortNumber], ['id' => $country['id']]);
            $sortNumber++;
        }

        $states = (new Query())
            ->select(['*'])
            ->from('{{%commerce_states}}')
            ->limit(null)
            ->orderBy(['name' => SORT_ASC])
            ->where(['sortOrder' => null])
            ->all();

        $sortNumber = 1000; //start at 1000 so it doesn't affect if any states had a sort previous
        foreach ($states as $state) {
            $this->update('{{%commerce_states}}', ['sortOrder' => $sortNumber], ['id' => $state['id']]);
            $sortNumber++;
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200617_172414_fix_country_state_sort_orders cannot be reverted.\n";
        return false;
    }
}
