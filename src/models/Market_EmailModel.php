<?php

namespace Craft;

/**
 * Class Market_EmailModel
 *
 * @property int    id
 * @property string name
 * @property string subject
 * @property string to
 * @property string bcc
 * @property bool   enabled
 * @property string templatePath
 *
 * @package Craft
 */
class Market_EmailModel extends BaseModel
{
	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'id'           => [AttributeType::Number, 'required' => true],
			'name'         => [AttributeType::String, 'required' => true],
			'subject'      => [AttributeType::String, 'required' => true],
			'to'           => [AttributeType::String, 'required' => true],
			'bcc'          => AttributeType::String,
			'enabled'      => [
				AttributeType::Bool,
				'required' => true,
				'default'  => 1
			],
			'templatePath' => [AttributeType::String, 'required' => true],
		];
	}
}