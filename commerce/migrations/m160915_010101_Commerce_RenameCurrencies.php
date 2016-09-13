<?php
namespace Craft;

class m160915_010101_Commerce_RenameCurrencies extends BaseMigration
{
	public function safeUp()
	{
		craft()->db->createCommand()->renameTable('commerce_currencies','commerce_paymentcurrencies');
	}
}
