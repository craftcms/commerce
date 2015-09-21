<?php

namespace Craft;

/**
 * Class Commerce_EmailRecord
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
class Commerce_EmailRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_emails';
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'name'         => [AttributeType::String, 'required' => true],
			'subject'      => [AttributeType::String, 'required' => true],
			'to'           => [AttributeType::String, 'required' => true],
			'bcc'          => AttributeType::String,
			'enabled'      => [AttributeType::Bool, 'required' => true],
			'templatePath' => [AttributeType::String, 'required' => true],
		];
	}
}