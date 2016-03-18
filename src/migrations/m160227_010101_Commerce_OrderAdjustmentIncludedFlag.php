<?php
namespace Craft;

class m160227_010101_Commerce_OrderAdjustmentIncludedFlag extends BaseMigration
{
	public function safeUp()
	{
		$this->addColumnAfter('commerce_orderadjustments','included',ColumnType::Bool,'amount');
	}
}
