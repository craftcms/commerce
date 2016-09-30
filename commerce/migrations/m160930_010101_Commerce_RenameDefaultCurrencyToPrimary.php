<?php
namespace Craft;

class m160930_010101_Commerce_RenameDefaultCurrencyToPrimary extends BaseMigration
{
	public function safeUp()
	{
		craft()->db->createCommand()->renameColumn('commerce_paymentcurrencies','default','primary');
	}
}
