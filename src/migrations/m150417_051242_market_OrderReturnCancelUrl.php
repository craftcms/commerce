<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150417_051242_market_OrderReturnCancelUrl extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
        $ordersTable = Market_OrderRecord::model()->getTableName();
        $ordersTable = craft()->db->addTablePrefix($ordersTable);
        craft()->db->createCommand("
            ALTER TABLE $ordersTable
            ADD COLUMN returnUrl VARCHAR(255) NULL AFTER lastIp,
            ADD COLUMN cancelUrl VARCHAR(255) NULL AFTER returnUrl
        ")->execute();
		return true;
	}
}
