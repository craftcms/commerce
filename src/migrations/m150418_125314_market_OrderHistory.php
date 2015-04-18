<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150418_125314_market_OrderHistory extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
        // Create the craft_market_orderhistories table
        craft()->db->createCommand()->createTable('market_orderhistories', [
            'prevStatusId' => ['column' => 'integer', 'required' => false],
            'newStatusId'  => ['column' => 'integer', 'required' => false],
            'orderId'      => ['maxLength' => 11, 'decimals' => 0, 'required' => true, 'unsigned' => false, 'length' => 10, 'column' => 'integer'],
            'userId'       => ['maxLength' => 11, 'decimals' => 0, 'required' => true, 'unsigned' => false, 'length' => 10, 'column' => 'integer'],
            'message'      => ['column' => 'text'],
        ], null, true);

        // Add foreign keys to craft_market_orderhistories
        craft()->db->createCommand()->addForeignKey('market_orderhistories', 'orderId', 'market_orders', 'id', 'CASCADE', 'CASCADE');
        craft()->db->createCommand()->addForeignKey('market_orderhistories', 'prevStatusId', 'market_orderstatuses', 'id', 'RESTRICT', 'CASCADE');
        craft()->db->createCommand()->addForeignKey('market_orderhistories', 'newStatusId', 'market_orderstatuses', 'id', 'RESTRICT', 'CASCADE');
        craft()->db->createCommand()->addForeignKey('market_orderhistories', 'userId', 'users', 'id', 'RESTRICT', 'CASCADE');


        return true;
    }
}
