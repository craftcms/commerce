<?php
namespace Craft;

class m160229_010104_Commerce_SoftDeleteAndReorderPaymentMethod extends BaseMigration
{
	public function safeUp()
	{
		$this->addColumnBefore('commerce_paymentmethods','isArchived',['column' => ColumnType::Bool, 'default' => 0],'dateUpdated');
		$this->addColumnBefore('commerce_paymentmethods','dateArchived',ColumnType::DateTime,'dateUpdated');
		$this->addColumnBefore('commerce_paymentmethods','sortOrder',ColumnType::Int,'dateUpdated');
		$this->dropColumn('commerce_paymentmethods','cpEnabled');

	}
}
