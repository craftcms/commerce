<?php
namespace Craft;

/**
 * Class Market_PaymentMethodRecord
 *
 * @property int    $id	The primary key and id of the Payment Method
 * @property string $class
 * @property string $name
 * @property array  $settings
 * @property string $type
 * @property bool   $cpEnabled
 * @property bool   $frontendEnabled
 * @package Craft
 */
class Market_PaymentMethodRecord extends BaseRecord
{
	/*
	 * The name of the table not including the craft db prefix e.g craft_
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return 'market_paymentmethods';
	}

	public function defineIndexes()
	{
		return array(
			array('columns' => array('class'), 'unique' => true),
		);
	}

	protected function defineAttributes()
	{
		return array(
			'class'           => array(AttributeType::String, 'required' => true),
			'name'            => array(AttributeType::String, 'required' => true),
			'settings'        => array(AttributeType::Mixed, 'required' => true),
			'cpEnabled'       => array(AttributeType::Bool, 'required' => true, 'default' => 0),
			'frontendEnabled' => array(AttributeType::Bool, 'required' => true, 'default' => 0),
		);
	}
}



