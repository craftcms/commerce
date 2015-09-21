<?php
namespace Craft;

class m150917_010101_Market_DropEmailTypeColumn extends BaseMigration
{
	public function safeUp ()
	{
		$this->dropColumn('market_emails','type');
		return true;
	}
}