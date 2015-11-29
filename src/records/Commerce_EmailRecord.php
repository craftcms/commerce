<?php
namespace Craft;

/**
 * Email record.
 *
 * @property int $id
 * @property string $name
 * @property string $subject
 * @property string $to
 * @property string $bcc
 * @property bool $enabled
 * @property string $templatePath
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_EmailRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_emails';
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'name' => [AttributeType::String, 'required' => true],
            'subject' => [AttributeType::String, 'required' => true],
            'to' => [AttributeType::String, 'required' => true],
            'bcc' => AttributeType::String,
            'enabled' => [AttributeType::Bool, 'required' => true],
            'templatePath' => [AttributeType::String, 'required' => true],
        ];
    }
}