<?php
namespace Craft;

class m150917_010102_Market_RenameCodeToHandletaxCatColumn extends BaseMigration
{
	public function safeUp ()
	{
		$this->renameColumn('market_taxcategories','code','handle');
		return true;
	}
}