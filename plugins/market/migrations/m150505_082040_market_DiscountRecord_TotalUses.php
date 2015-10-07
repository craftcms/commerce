<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of
 * mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150505_082040_market_DiscountRecord_TotalUses extends BaseMigration
{
    /**
     * Any migration code in here is wrapped inside of a transaction.
     *
     * @return bool
     */
    public function safeUp()
    {
        craft()->db->createCommand("
		ALTER TABLE craft_market_discounts
		  ADD COLUMN totalUses INT UNSIGNED DEFAULT 0  NOT NULL AFTER totalUseLimit;
		")->execute();

        return true;
    }
}