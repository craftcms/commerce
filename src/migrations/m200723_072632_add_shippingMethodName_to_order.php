<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query as CraftQuery;

/**
 * m200723_072632_add_shippingMethodName_to_order migration.
 */
class m200723_072632_add_shippingMethodName_to_order extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_orders}}', 'shippingMethodName')) {
            $this->addColumn('{{%commerce_orders}}', 'shippingMethodName', $this->string());
        }

        $shippingMethods = (new CraftQuery())
            ->select(['name', 'handle'])
            ->from('{{%commerce_shippingmethods}}')
            ->where(['not', ['name' => null]])
            ->all();

        if (!empty($shippingMethods)) {
            foreach ($shippingMethods as $shippingMethod) {
                $this->update('{{%commerce_orders}}', ['shippingMethodName' => $shippingMethod['name']], ['shippingMethodHandle' => $shippingMethod['handle']]);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200723_072632_add_shippingMethodName_to_order cannot be reverted.\n";
        return false;
    }
}
