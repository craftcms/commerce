<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150418_110118_market_OrderStatusId extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
        $table = Market_OrderRecord::model()->getTableName();
        $table = craft()->db->addTablePrefix($table);

        craft()->db->createCommand("
            ALTER TABLE $table
                DROP COLUMN `state`,
                ADD COLUMN `statusId` INT(11) NULL AFTER `customerId`
        ")->execute();

        craft()->db->createCommand()->addForeignKey('market_orders', 'statusId', 'market_orderstatuses', 'id', 'RESTRICT', 'CASCADE');

		return true;
	}
}
