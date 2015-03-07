<?php
namespace Craft;

class m150306_010101_Market_AddTypeToProductType extends BaseMigration
{
	public function safeUp()
	{
		// Allow transforms to have a format
		$this->addColumnAfter('market_producttypes', 'type', array(ColumnType::Varchar, 'required' => true), 'handle');
		return true;
	}
}