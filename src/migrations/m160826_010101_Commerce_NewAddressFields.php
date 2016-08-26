<?php
namespace Craft;

class m160826_010101_Commerce_NewAddressFields extends BaseMigration
{
	public function safeUp()
	{
		$this->addColumnBefore('commerce_addresses', 'attention', ColumnType::Varchar, 'firstName');
		$this->addColumnBefore('commerce_addresses', 'title', ColumnType::Varchar, 'firstName');
		$this->addColumnAfter('commerce_addresses', 'businessId', ColumnType::Varchar, 'businessTaxId');
	}
}
