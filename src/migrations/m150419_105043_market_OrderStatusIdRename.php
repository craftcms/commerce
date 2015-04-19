<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150419_105043_market_OrderStatusIdRename extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
        craft()->db->createCommand()->dropForeignKey('market_orders', 'statusId');
        craft()->db->createCommand()->renameColumn('market_orders', 'statusId', 'orderStatusId');
        craft()->db->createCommand()->addForeignKey('market_orders', 'orderStatusId', 'market_orderstatuses', 'id', 'RESTRICT', 'CASCADE');

        return true;
	}
}
