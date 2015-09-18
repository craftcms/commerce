<?php
namespace Craft;

class m150919_010101_Market_AddHasDimensionsToProductType extends BaseMigration
{
	public function safeUp ()
	{
		$this->addColumnAfter('market_producttypes', 'hasDimensions', ColumnType::Bool, 'handle');

		return true;
	}
}