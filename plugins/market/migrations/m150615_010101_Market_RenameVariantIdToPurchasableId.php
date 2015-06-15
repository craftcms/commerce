<?php
namespace Craft;

class m150615_010101_Market_RenameVariantIdToPurchasableId extends BaseMigration
{
	public function safeUp()
	{
		$this->dropForeignKey('market_lineitems','variantId');
		$this->renameColumn('market_lineitems','variantId','purchasableId');
		$this->addForeignKey('market_lineitems','purchasableId','elements','id','SET NULL','CASCADE');
		return true;
	}
}