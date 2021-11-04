<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\helpers\UrlHelper;
use DateTime;

/**
 * Order status model.
 *
 * @property string $cpEditUrl
 * @property array $emailIds
 * @property-read array $config
 * @property string $labelHtml
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItemStatus extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var string Color
     */
    public string $color = 'green';

    /**
     * @var int|null Sort order
     */
    public ?int $sortOrder = null;

    /**
     * @var bool Default status
     */
    public bool $default = false;

    /**
     * @var bool Whether the order status is archived.
     */
    public bool $isArchived = false;

    /**
     * @var DateTime|null Archived Date
     */
    public ?DateTime $dateArchived = null;

    /**
     * @var string|null UID
     */
    public ?string $uid = null;

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
    protected function defineRules(): array
    {
        return [
            [['name', 'handle'], 'required'],
        ];
    }

    /**
     * @inerhitdoc
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'labelHtml';

        return $fields;
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

    /**
     * Returns the config for this status.
     *
     * @return array
     * @since 3.2.2
     */
    public function getConfig(): array
    {
        return [
            'name' => $this->name,
            'handle' => $this->handle,
            'color' => $this->color,
            'sortOrder' => $this->sortOrder ?: 9999,
            'default' => $this->default,
        ];
    }
}
