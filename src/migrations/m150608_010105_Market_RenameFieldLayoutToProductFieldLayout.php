<?php
namespace Craft;

class m150608_010105_Market_RenameFieldLayoutToProductFieldLayout extends BaseMigration
{
	public function safeUp()
	{
		$this->dropForeignKey('market_producttypes','fieldLayoutId');
		$this->renameColumn('market_producttypes','fieldLayoutId','productFieldLayoutId');
		$this->addForeignKey('market_producttypes','productFieldLayoutId','fieldlayouts','id','SET NULL');
		return true;
	}
}