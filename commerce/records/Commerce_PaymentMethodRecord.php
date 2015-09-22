<?php
namespace Craft;

/**
 * Payment method id.
 *
 * @property int    $id
 * @property string $class
 * @property string $name
 * @property array  $settings
 * @property string $type
 * @property bool   $cpEnabled
 * @property bool   $frontendEnabled
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_PaymentMethodRecord extends BaseRecord
{
	/**
	 * The name of the table not including the craft db prefix e.g craft_
	 *
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_paymentmethods';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['class'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'class'           => [AttributeType::String, 'required' => true],
			'name'            => [AttributeType::String, 'required' => true],
			'settings'        => [AttributeType::Mixed],
			'cpEnabled'       => [
				AttributeType::Bool,
				'required' => true,
				'default'  => 0
			],
			'frontendEnabled' => [
				AttributeType::Bool,
				'required' => true,
				'default'  => 0
			],
		];
	}
}



