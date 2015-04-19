<?php

namespace Craft;

/**
 * Class Market_EmailRecord
 *
 * @property int    id
 * @property string name
 * @property string subject
 * @property string to
 * @property string bcc
 * @property bool   enabled
 * @property string type
 * @property string templatePath
 *
 * @package Craft
 */
class Market_EmailRecord extends BaseRecord
{
    const TYPE_PLAIN = 'plain_text';
    const TYPE_HTML = 'html';

	public function getTableName()
	{
		return 'market_emails';
	}

	protected function defineAttributes()
	{
		return [
			'name'      => [AttributeType::String, 'required' => true],
			'subject'   => [AttributeType::String, 'required' => true],
			'to'        => [AttributeType::Email, 'required' => true],
			'bcc'       => AttributeType::Email,
			'type'      => [AttributeType::Enum, 'required' => true, 'values' => [self::TYPE_PLAIN, self::TYPE_HTML]],
			'enabled'   => [AttributeType::Bool, 'required' => true],
			'templatePath' => [AttributeType::String, 'required' => true],
		];
	}
}