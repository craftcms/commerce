<?php
namespace Craft;

class m160226_010101_Commerce_OrderStatusSortOrder extends BaseMigration
{
	public function safeUp()
	{
		$this->addColumnAfter('commerce_orderstatuses','sortOrder',ColumnType::Int,'color');
	}
}
