<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Order status model.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string $color
 * @property bool $default
 *
 * @property Commerce_EmailModel[] $emails
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_OrderStatusModel extends BaseModel
{
    use Commerce_ModelRelationsTrait;

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
        return (string)$this->name;
    }

    /**
     * @return array
     */
    public function getEmailsIds()
    {
        return array_map(function (Commerce_EmailModel $email) {
            return $email->id;
        }, $this->emails);
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
            'default' => [
                AttributeType::Bool,
                'default' => 0,
                'required' => true
            ],
        ];
    }
}