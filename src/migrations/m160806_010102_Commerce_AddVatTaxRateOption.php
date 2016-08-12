<?php
namespace Craft;

class m160806_010102_Commerce_AddVatTaxRateOption extends BaseMigration
{
	public function safeUp()
	{
		$this->addColumnAfter('commerce_taxrates','isVat', ['column' => ColumnType::Bool, 'default' => 0],'include');
	}
}
