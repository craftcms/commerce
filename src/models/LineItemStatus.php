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
 * @property string $labelHtml
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItemStatus extends Model
{
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


    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['typecast'] = [
            'class' => AttributeTypecastBehavior::className(),
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
}
