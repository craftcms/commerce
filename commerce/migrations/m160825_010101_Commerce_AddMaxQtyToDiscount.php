<?php
namespace Craft;

class m160825_010101_Commerce_AddMaxQtyToDiscount extends BaseMigration
{
	public function safeUp()
	{
		$this->addColumnAfter('commerce_discounts','maxPurchaseQty',[ColumnType::Int, 'default' => 0],'purchaseQty');
	}
}
