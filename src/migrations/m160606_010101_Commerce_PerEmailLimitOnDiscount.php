<?php
namespace Craft;

class m160606_010101_Commerce_PerEmailLimitOnDiscount extends BaseMigration
{
	public function safeUp()
	{
		$this->addColumnAfter('commerce_discounts','perEmailLimit',[ColumnType::Int, 'default' => 0],'perUserLimit');
	}
}
