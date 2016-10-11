<?php
namespace Craft;

class m151025_010101_Commerce_AddHandleToShippingMethod extends BaseMigration
{
    public function safeUp()
    {
        // Add handle to shipping methods
        $this->addColumnAfter('commerce_shippingmethods','handle',ColumnType::Varchar,'name');
        $this->dropColumn('commerce_shippingmethods','default');

        // get all current shipping methods
        $shippingMethods = craft()->db->createCommand()
        ->select('id')
        ->from('commerce_shippingmethods')
        ->queryColumn();

        // add a default handle to all shipping methods
        foreach($shippingMethods as $method){
            $data = array('handle' => "shippingMethod-".$method);
            craft()->db->createCommand()->update('commerce_shippingmethods', $data, 'id = :id', array(':id' => $method));
        }

        // remove the order's relation to shippingmethods table
        MigrationHelper::dropForeignKeyIfExists('commerce_orders',['shippingMethodId']);

        // rename
        Craft()->db->createCommand("
            ALTER TABLE {{commerce_orders}}
            CHANGE `shippingMethodId` `shippingMethod` VARCHAR(255) NULL
        ")->execute();

        // get all orders with shipping method
        $orders = craft()->db->createCommand()
            ->select('id, shippingMethod')
            ->from('commerce_orders')
            ->queryAll();

        // set the orders shipping method to the new default handles
        foreach($orders as $order){
            $data = array('shippingMethod' => "shippingMethod-".$order['shippingMethod']);
            craft()->db->createCommand()->update('commerce_orders', $data, 'id = :id', array(':id' => $order['id']));
        }
        
        // set all orders to the default currency
        $settings = craft()->db->createCommand()->select('settings')->from('plugins')->where("class = :xclass", [':xclass' => 'Commerce'])->queryScalar();
        $settings = JsonHelper::decode($settings);
        $defaultCurrency = $settings['defaultCurrency'];
        $data = array('currency' => $defaultCurrency);
        craft()->db->createCommand()->update('commerce_orders', $data);

        return true;
    }
}
