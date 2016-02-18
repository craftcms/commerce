<?php
namespace Craft;

class m160215_010101_Commerce_ConsistentDecimalType extends BaseMigration
{
	public function safeUp()
	{
		$query = <<<EOT
ALTER TABLE `{{commerce_taxrates}}`
MODIFY rate decimal(14,4);

ALTER TABLE `{{commerce_shippingrules}}`
MODIFY minTotal decimal(14,4),
MODIFY maxTotal decimal(14,4),
MODIFY minWeight decimal(14,4),
MODIFY maxWeight decimal(14,4),
MODIFY baseRate decimal(14,4),
MODIFY perItemRate decimal(14,4),
MODIFY weightRate decimal(14,4),
MODIFY percentageRate decimal(14,4),
MODIFY minRate decimal(14,4),
MODIFY maxRate decimal(14,4);

ALTER TABLE `{{commerce_sales}}`
MODIFY discountAmount decimal(14,4);

ALTER TABLE `{{commerce_transactions}}`
MODIFY amount decimal(14,4);

ALTER TABLE `{{commerce_orderadjustments}}`
MODIFY amount decimal(14,4);

ALTER TABLE `{{commerce_discounts}}`
MODIFY baseDiscount decimal(14,4),
MODIFY perItemDiscount decimal(14,4),
MODIFY percentDiscount decimal(14,4);
EOT;

	craft()->db->createCommand($query)->query();

	}
}
