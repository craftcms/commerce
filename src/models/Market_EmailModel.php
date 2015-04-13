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
 * @property string type
 * @property string templatePath
 *
 * @package Craft
 */
class Market_EmailModel extends BaseModel
{
	protected function defineAttributes()
	{
        return [
            'id'        => [AttributeType::Number, 'required' => true],
            'name'      => [AttributeType::String, 'required' => true],
            'subject'   => [AttributeType::String, 'required' => true],
            'to'        => [AttributeType::Email, 'required' => true],
            'bcc'       => AttributeType::Email,
            'type'      => [AttributeType::Enum, 'required' => true, 'values' => ['plain_text', 'html']],
            'enabled'   => [AttributeType::Bool, 'required' => true, 'default' => 1],
            'templatePath' => [AttributeType::String, 'required' => true],
        ];
	}
}