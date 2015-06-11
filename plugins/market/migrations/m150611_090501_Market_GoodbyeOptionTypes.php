<?php
namespace Craft;

class m150611_090501_Market_GoodbyeOptionTypes extends BaseMigration
{
	public function safeUp()
	{
		// Allow transforms to have a format
		$this->addColumnAfter('market_producttypes', 'variantFieldLayoutId', array(ColumnType::Int, 'required' => false), 'fieldLayoutId');
		$this->addForeignKey('market_producttypes','variantFieldLayoutId','fieldlayouts','id','SET NULL');
		return true;
	}
}