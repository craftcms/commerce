<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m170721_150000_paymentmethod_type_changes
 */
class m170721_150000_paymentmethod_type_changes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $rows = (new Query())
            ->select(['id', 'type'])
            ->from('{{%commerce_paymentmethods}}')
            ->all();

        foreach ($rows as $row) {
            $type = preg_replace('/gateways\\\\(.*)_GatewayAdapter/i', 'paymentmethods\\\\$1', $row['type']);
            $this->update('{{%commerce_paymentmethods}}', ['type' => $type], [ 'id' => $row['id']]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170721_150000_paymentmethod_type_changes cannot be reverted.\n";


        return false;
    }
}
