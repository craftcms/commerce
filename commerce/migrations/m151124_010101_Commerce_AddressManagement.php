<?php
namespace Craft;

class m151124_010101_Commerce_AddressManagement extends BaseMigration
{
    public function safeUp()
    {
        $table = MigrationHelper::getTable('commerce_addresses');

        MigrationHelper::dropAllForeignKeysOnTable($table);
        MigrationHelper::dropAllIndexesOnTable($table);

        if(!MigrationHelper::getTable('commerce_customers_addresses')){
            // Create the craft_commerce_customers_addresses table
            craft()->db->createCommand()->createTable('commerce_customers_addresses', array(
                'customerId' => array('column' => 'integer', 'required' => true),
                'addressId' => array('column' => 'integer', 'required' => true),
            ), null, true);

            // Add indexes to craft_commerce_customers_addresses
            craft()->db->createCommand()->createIndex('commerce_customers_addresses', 'customerId', false);
            craft()->db->createCommand()->createIndex('commerce_customers_addresses', 'addressId', false);
            craft()->db->createCommand()->createIndex('commerce_customers_addresses', 'customerId,addressId', true);

            // Add foreign keys to craft_commerce_customers_addresses
            craft()->db->createCommand()->addForeignKey('commerce_customers_addresses', 'customerId', 'commerce_customers', 'id', 'CASCADE', 'CASCADE');
            craft()->db->createCommand()->addForeignKey('commerce_customers_addresses', 'addressId', 'commerce_addresses', 'id', 'CASCADE', 'CASCADE');
        }

        // Get all address records
        $addresses = craft()->db->createCommand()
            ->select('*')
            ->from('commerce_addresses')
            ->queryAll();

        // create each customer address record
        foreach ($addresses as $address) {
            if (isset($address['customerId'])) {
                $data = ['customerId' => $address['customerId'], 'addressId' => $address['id']];
                if(!craft()->db->createCommand()->select('*')->from('commerce_customers_addresses')->where($data)->queryScalar()){
                    craft()->db->createCommand()->insert('commerce_customers_addresses', $data);
                };
            }
        }

        $table = craft()->db->schema->getTable('craft_commerce_addresses');
        if (isset($table->columns['customerId'])) {
            $this->dropColumn('commerce_addresses', 'customerId');
        }

        // Add foreign keys to craft_commerce_addresses
        craft()->db->createCommand()->addForeignKey('commerce_addresses', 'countryId', 'commerce_countries', 'id', 'RESTRICT', 'CASCADE');
        craft()->db->createCommand()->addForeignKey('commerce_addresses', 'stateId', 'commerce_states', 'id', 'RESTRICT', 'CASCADE');

        $orders = craft()->db->createCommand()
            ->select('*')
            ->from('commerce_orders')
            ->queryAll();

        // Migration address data in json to address table
        foreach ($orders as $order) {
            $addressTypes = ['shippingAddress', 'billingAddress'];
            foreach ($addressTypes as $type) {
                if ($order[$type.'Data']) {
                    $address = json_decode($order[$type.'Data'], true);
                    if (isset($address['company'])) {
                        $address['businessName'] = $address['company']; // account for field rename
                        unset($address['company']);
                    }
                    unset($address['customerId']); // no longer needing in address table
                    unset($address['id']); // getting a new id when inserting into address table
                    $address = array_filter($address);
                    if (!empty($address)) {
                        craft()->db->createCommand()->insert('commerce_addresses', $address);
                        $id = craft()->db->getLastInsertID();
                        $insertData = [$type.'Id' => $id, $type.'Data' => json_encode([])];
                        craft()->db->createCommand()->update('commerce_orders',$insertData,'id = :idx', [':idx' => $order['id']]);
                    }
                }
            }
        }

        $this->dropColumn('commerce_orders','shippingAddressData');
        $this->dropColumn('commerce_orders','billingAddressData');

    }
}
