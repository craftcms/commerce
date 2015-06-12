<?php
namespace Craft;

class m150612_090501_Market_HelloVariantElements extends BaseMigration
{
	public function safeUp()
	{
		MigrationHelper::makeElemental('market_variants','Market_Variant',true);
		return true;
	}
}