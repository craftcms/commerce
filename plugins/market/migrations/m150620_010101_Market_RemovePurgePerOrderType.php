<?php
namespace Craft;

class m150620_010101_Market_RemovePurgePerOrderType extends BaseMigration
{
	public function safeUp()
	{
		$this->dropColumn('market_ordertypes','purgeIncompletedCartDuration');
		return true;
	}
}