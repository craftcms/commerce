<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;
use DateTime;

/**
 * Order status model.
 *
 * @property string $cpEditUrl
 * @property array $emailIds
 * @property string $labelHtml
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItemStatus extends Model
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

    /**
     * @var bool Whether the order status is archived.
     */
    public $isArchived = false;

    /**
     * @var DateTime Archived Date
     */
    public $dateArchived;

    /**
     * @var string UID
     */
    public $uid;

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
            [['name', 'handle'], 'required'],
        ];
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/lineitemstatuses/' . $this->id);
    }

    /**
     * @return string
     */
    public function getLabelHtml(): string
    {
        return sprintf('<span class="commerceStatusLabel"><span class="status %s"></span>%s</span>', $this->color, $this->name);
    }
}
