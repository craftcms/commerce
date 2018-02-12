<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;

/**
 * Order status model.
 *
 * @property string  $cpEditUrl
 * @property array   $emailIds
 * @property Email[] $emails
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class OrderStatus extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Handle
     */
    public $handle;

    /**
     * @var string Color
     */
    public $color = 'green';

    /**
     * @var int Sort order
     */
    public $sortOrder;

    /**
     * @var bool Default status
     */
    public $default;

    // Public Methods
    // =========================================================================

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
    public function rules(): array
    {
        return [
            [['name', 'handle', 'default'], 'required']
        ];
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/orderstatuses/'.$this->id);
    }

    /**
     * @return array
     */
    public function getEmailIds(): array
    {
        return array_column($this->getEmails(), 'id');
    }

    /**
     * @return Email[]
     */
    public function getEmails(): array
    {
        return Plugin::getInstance()->getEmails()->getAllEmailsByOrderStatusId($this->id);
    }

    /**
     * @return string
     */
    public function htmlLabel(): string
    {
        return sprintf('<span class="commerceStatusLabel"><span class="status %s"></span> %s</span>', $this->color, $this->name);
    }
}
