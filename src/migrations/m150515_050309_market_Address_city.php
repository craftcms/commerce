<?php
namespace Craft;

class m150515_050309_market_Address_city extends BaseMigration
{
	public function safeUp()
	{
		// Allow transforms to have a format
		$this->addColumnAfter('market_addresses', 'city', array(ColumnType::Varchar, 'required' => false), 'address2');
		return true;
	}
}