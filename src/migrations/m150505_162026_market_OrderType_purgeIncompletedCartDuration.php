<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150505_162026_market_OrderType_purgeIncompletedCartDuration extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
		craft()->db->createCommand("
		ALTER TABLE `craft_market_ordertypes`
		  ADD COLUMN `purgeIncompletedCartDuration` VARCHAR(20) NULL AFTER `shippingMethodId`;
		")->execute();
		return true;
	}
}
