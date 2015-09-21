<?php
namespace Craft;

class m150908_010101_Market_RenameMasterToImplicit extends BaseMigration
{
	public function safeUp ()
	{
		$this->renameColumn('market_variants','isMaster','isImplicit');
		return true;
	}
}