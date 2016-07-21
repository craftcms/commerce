<?php
namespace Craft;

/**
 * Order status model.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string $color
 * @property int $sortOrder
 * @property bool $default
 *
 * @property Commerce_EmailModel[] $emails
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_OrderStatusModel extends BaseModel
{
    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/orderstatuses/' . $this->id);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getAttribute('name');
    }

    /**
     * @return Commerce_EmailModel[]
     */
    public function getEmails()
    {
        return craft()->commerce_orderStatuses->getAllEmailsByOrderStatusId($this->id);
    }

    /**
     * @return array
     */
    public function getEmailIds()
    {
        return array_map(function (Commerce_EmailModel $email) {
            return $email->id;
        }, $this->getEmails());
    }

    /**
     * @return string
     */
    public function htmlLabel()
    {
        return sprintf('<span class="commerceStatusLabel"><span class="status %s"></span> %s</span>',
            $this->color, $this->name);
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'name' => [AttributeType::String, 'required' => true],
            'handle' => [AttributeType::Handle, 'required' => true],
            'color' => [AttributeType::String, 'default' => 'green'],
            'sortOrder' => AttributeType::Number,
            'default' => [
                AttributeType::Bool,
                'default' => 0,
                'required' => true
            ],
        ];
    }
}