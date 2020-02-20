<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\OrderStatus as OrderStatusRecord;
use craft\db\SoftDeleteTrait;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;
use DateTime;
use yii\behaviors\AttributeTypecastBehavior;
use craft\validators\UniqueValidator;
/**
 * Order status model.
 *
 * @property string $cpEditUrl
 * @property array $emailIds
 * @property string $labelHtml
 * @property string $displayName
 * @property Email[] $emails
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderStatus extends Model
{
    use SoftDeleteTrait {
        behaviors as softDeleteBehaviors;
    }


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
     * @var string Description
     */
    public $description;

    /**
     * @var int Sort order
     */
    public $sortOrder;

    /**
     * @var bool Default status
     */
    public $default;

    /**
     * @var bool Default status
     */
    public $dateDeleted;

    /**
     * @var string UID
     */
    public $uid;



    /**
     * @return array
     */
    public function behaviors(): array
    {
        $behaviors = $this->softDeleteBehaviors();

        $behaviors['typecast'] = [
            'class' => AttributeTypecastBehavior::className(),
            'attributeTypes' => [
                'id' => AttributeTypecastBehavior::TYPE_INTEGER,
                'name' => AttributeTypecastBehavior::TYPE_STRING,
                'handle' => AttributeTypecastBehavior::TYPE_STRING,
                'color' => AttributeTypecastBehavior::TYPE_STRING,
                'sortOrder' => AttributeTypecastBehavior::TYPE_INTEGER,
                'default' => AttributeTypecastBehavior::TYPE_BOOLEAN,
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
        return (string)$this->getDisplayName();
    }

    /**
     * @return string
     * @since 2.2
     */
    public function getDisplayName(): string
    {
        if ($this->dateDeleted !== null)
        {
            return $this->name . Plugin::t(' (Trashed)');
        }

        return $this->name;
    }

    /**
     * @return array
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['handle'], UniqueValidator::class, 'targetClass' => OrderStatusRecord::class];
        $rules[] = [
            ['handle'],
            HandleValidator::class,
            'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title', 'create-new']
        ];

        return $rules;
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/orderstatuses/' . $this->id);
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
        return $this->id ? Plugin::getInstance()->getEmails()->getAllEmailsByOrderStatusId($this->id) : [];
    }

    /**
     * @return string
     */
    public function getLabelHtml(): string
    {
        return sprintf('<span class="commerceStatusLabel"><span class="status %s"></span>%s</span>', $this->color, $this->getDisplayName());
    }

    /**
     * @return bool
     * @since 2.2
     */
    public function canDelete(): bool
    {
        return !Order::find()->trashed(null)->orderStatus($this)->one();
    }
}
