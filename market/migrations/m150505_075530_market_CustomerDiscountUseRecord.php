<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of
 * mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150505_075530_market_CustomerDiscountUseRecord extends BaseMigration
{
    /**
     * Any migration code in here is wrapped inside of a transaction.
     *
     * @return bool
     */
    public function safeUp()
    {
        // Create the craft_market_customer_discountuses table
        craft()->db->createCommand()->createTable('market_customer_discountuses',
            [
                'discountId' => [
                    'maxLength' => 11,
                    'decimals'  => 0,
                    'required'  => true,
                    'unsigned'  => false,
                    'length'    => 10,
                    'column'    => 'integer'
                ],
                'customerId' => [
                    'maxLength' => 11,
                    'decimals'  => 0,
                    'required'  => true,
                    'unsigned'  => false,
                    'length'    => 10,
                    'column'    => 'integer'
                ],
                'uses'       => [
                    'maxLength' => 11,
                    'decimals'  => 0,
                    'required'  => true,
                    'unsigned'  => true,
                    'length'    => 10,
                    'column'    => 'integer'
                ],
            ], null, true);

        // Add indexes to craft_market_customer_discountuses
        craft()->db->createCommand()->createIndex('market_customer_discountuses',
            'customerId,discountId', true);

        // Add foreign keys to craft_market_customer_discountuses
        craft()->db->createCommand()->addForeignKey('market_customer_discountuses',
            'discountId', 'market_discounts', 'id', 'CASCADE', 'CASCADE');
        craft()->db->createCommand()->addForeignKey('market_customer_discountuses',
            'customerId', 'market_customers', 'id', 'CASCADE', 'CASCADE');

        return true;
    }
}
