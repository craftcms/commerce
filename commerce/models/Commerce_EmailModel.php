<?php
namespace Craft;

/**
 * Email model.
 *
 * @property int $id
 * @property string $name
 * @property string $subject
 * @property string $recipientType
 * @property string $to
 * @property string $bcc
 * @property bool $enabled
 * @property string $templatePath
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_EmailModel extends BaseModel
{
    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => [AttributeType::Number, 'required' => true],
            'name' => [AttributeType::String, 'required' => true],
            'subject' => [AttributeType::String, 'required' => true],
            'recipientType' => [AttributeType::Enum, 'values' => [Commerce_EmailRecord::TYPE_CUSTOMER, Commerce_EmailRecord::TYPE_CUSTOM], 'default' => Commerce_EmailRecord::TYPE_CUSTOM],
            'to' => AttributeType::String,
            'bcc' => AttributeType::String,
            'enabled' => [
                AttributeType::Bool,
                'required' => true,
                'default' => 1
            ],
            'templatePath' => [AttributeType::String, 'required' => true],
        ];
    }
}
