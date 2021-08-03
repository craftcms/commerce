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
use yii\behaviors\AttributeTypecastBehavior;

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
     * @var int ID
     */
    public int $id;

    /**
     * @var string Name
     */
    public string $name;

    /**
     * @var string Handle
     */
    public string $handle;

    /**
     * @var string Color
     */
    public string $color = 'green';

    /**
     * @var int Sort order
     */
    public int $sortOrder;

    /**
     * @var bool Default status
     */
    public bool $default;

    /**
     * @var bool Whether the order status is archived.
     */
    public bool $isArchived = false;

    /**
     * @var DateTime Archived Date
     */
    public DateTime $dateArchived;

    /**
     * @var string UID
     */
    public string $uid;


    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['typecast'] = [
            'class' => AttributeTypecastBehavior::class,
            'attributeTypes' => [
                'id' => AttributeTypecastBehavior::TYPE_INTEGER,
                'name' => AttributeTypecastBehavior::TYPE_STRING,
                'handle' => AttributeTypecastBehavior::TYPE_STRING,
                'color' => AttributeTypecastBehavior::TYPE_STRING,
                'sortOrder' => AttributeTypecastBehavior::TYPE_INTEGER,
                'default' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                'isArchived' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                'uid' => AttributeTypecastBehavior::TYPE_STRING,
            ]
        ];

        return $behaviors;
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
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle'], 'required'];

        return $rules;
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
