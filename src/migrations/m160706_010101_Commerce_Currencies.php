<?php
namespace Craft;

class m160706_010101_Commerce_Currencies extends BaseMigration
{
	public function safeUp()
	{
		craft()->db->createCommand()->createTable('commerce_currencies', array(
			'name' => array('required' => true),
			'iso'  => array('required' => true, 'maxLength' => 3),
			'rate' => array('maxLength' => 10, 'decimals' => 4, 'default' => 0, 'required' => true, 'unsigned' => false, 'length' => 14, 'column' => 'decimal'),
		), null, true);

		craft()->db->createCommand()->createIndex('commerce_currencies', 'iso', true);

	}
}
