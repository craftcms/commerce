<?php
namespace Craft;

class m160226_010102_Commerce_isCompleted extends BaseMigration
{
	public function safeUp()
	{
		$this->addColumnBefore('commerce_orders','isCompleted',ColumnType::Bool,'dateOrdered');
		craft()->db->createCommand()->update('commerce_orders', ['isCompleted' => true], 'dateOrdered IS NOT NULL');
	}
}
