<?php
namespace Craft;

class m160706_010101_Commerce_Currencies extends BaseMigration
{
	public function safeUp()
	{
		craft()->db->createCommand()->createTable('commerce_currencies', [
			'iso'     => ['required' => true, 'maxLength' => 3],
			'default' => ['maxLength' => 1, 'default' => false, 'required' => true, 'column' => 'tinyint', 'unsigned' => true],
			'rate'    => ['maxLength' => 10, 'decimals' => 4, 'default' => 0, 'required' => true, 'unsigned' => false, 'length' => 14, 'column' => 'decimal'],
		], null, true);

		craft()->db->createCommand()->createIndex('commerce_currencies', 'iso', true);

		$this->addColumnAfter('commerce_orders', 'paymentCurrency', ColumnType::Varchar, 'currency');

		$this->addColumnAfter('commerce_transactions', 'paymentCurrency', ColumnType::Varchar, 'status');
		$this->addColumnAfter('commerce_transactions', 'currency', ColumnType::Varchar, 'status');
		$this->addColumnAfter('commerce_transactions', 'paymentRate', 'decimal(14,4) DEFAULT NULL', 'amount');
		$this->addColumnAfter('commerce_transactions', 'paymentAmount', 'decimal(14,4) DEFAULT NULL', 'amount');

		// Create primary currency
		$settings = craft()->db->createCommand()->select('settings')->from('plugins')->where("class = :xclass", [':xclass' => 'Commerce'])->queryScalar();
		$settings = JsonHelper::decode($settings);
		$primaryCurrency = $settings['defaultCurrency'];
		craft()->db->createCommand()->insert('commerce_currencies', ['iso' => $primaryCurrency, 'rate' => 1, 'default' => 1]);

        // set all orders to the original currency
        $data = array('paymentCurrency' => $primaryCurrency, 'currency' => $primaryCurrency);
        craft()->db->createCommand()->update('commerce_orders', $data);

        // set all transactions to the original currency
		$data = ['paymentCurrency' => $primaryCurrency, 'currency' => $primaryCurrency, 'paymentRate' => 1, 'paymentAmount' => new \CDbExpression('amount')];
		craft()->db->createCommand()->update('commerce_transactions', $data);
	}
}
