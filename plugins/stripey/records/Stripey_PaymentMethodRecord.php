<?php
namespace Craft;

/**
 * Class Stripey_PaymentMethodRecord
 *
 * @property int    $id
 * @property string $class
 * @property string $name
 * @property array  $settings
 * @property string $type
 * @property bool   $cpEnabled
 * @property bool   $frontendEnabled
 * @package Craft
 */
class Stripey_PaymentMethodRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'stripey_paymentmethods';
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

	public function defineIndexes()
	{
		return array(
			array('columns' => array('class'), 'unique' => true),
		);
	}
}



