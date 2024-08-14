<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\HasStoreInterface;
use craft\commerce\base\Model;
use craft\commerce\base\StoreTrait;
use craft\commerce\db\Table;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\OrderStatus as OrderStatusRecord;
use craft\db\SoftDeleteTrait;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use DateTime;
use yii\base\InvalidConfigException;

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
class OrderStatus extends Model implements HasStoreInterface
{
    use SoftDeleteTrait {
        SoftDeleteTrait::behaviors as softDeleteBehaviors;
    }
    use StoreTrait;

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
     * @var string|null Description
     */
    public ?string $description = null;

    /**
     * @var int|null Sort order
     */
    public ?int $sortOrder = null;

    /**
     * @var bool Default status
     */
    public bool $default = false;

    /**
     * @var DateTime|null Date deleted
     */
    public ?DateTime $dateDeleted = null;

    /**
     * @var string|null UID
     */
    public ?string $uid = null;

    public function behaviors(): array
    {
        return $this->softDeleteBehaviors();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getDisplayName();
    }

    /**
     * @since 2.2
     */
    public function getDisplayName(): string
    {
        if ($this->dateDeleted !== null) {
            return Craft::t('commerce', '{name} (Trashed)', ['name' => $this->name]);
        }

        return $this->name ?? '';
    }

    protected function defineRules(): array
    {
        return [
            [['name', 'handle'], 'required'],
            [['handle'],
                UniqueValidator::class,
                'targetClass' => OrderStatusRecord::class,
                'targetAttribute' => ['handle', 'storeId'],
                'message' => '{attribute} "{value}" has already been taken.',
            ],
            [
                ['handle'],
                HandleValidator::class,
                'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title', 'create'],
            ],
            [['id', 'color', 'description', 'default', 'sortOrder', 'dateDeleted', 'uid', 'storeId'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'emails';
        $fields[] = 'emailIds';
        $fields[] = 'labelHtml';

        return $fields;
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/orderstatuses/' . $this->getStore()->handle . '/' . $this->id);
    }

    /**
     * @throws InvalidConfigException
     */
    public function getEmailIds(): array
    {
        return array_column($this->getEmails(), 'id');
    }

    /**
     * @return Email[]
     * @throws InvalidConfigException
     */
    public function getEmails(): array
    {
        return $this->id ? Plugin::getInstance()->getEmails()->getAllEmailsByOrderStatusId($this->id) : [];
    }

    public function getLabelHtml(): string
    {
        return sprintf('<span class="commerceStatusLabel nowrap"><span class="status %s"></span>%s</span>', $this->color, Html::encode($this->getDisplayName()));
    }

    /**
     * @since 2.2
     */
    public function canDelete(): bool
    {
        /** @var OrderQuery $orderQuery */
        $orderQuery = Order::find()->trashed(null);
        return !$orderQuery->orderStatus($this)->one() && !$this->default;
    }

    /**
     * Returns the config for this status.
     *
     * @since 5.0.3
     */
    public function getConfig(?array $emailIds = null): array
    {
        if ($emailIds === null) {
            $emailIds = $this->getEmailIds();
        }

        $emails = !empty($emailIds) ? Db::uidsByIds(Table::EMAILS, $emailIds) : [];
        return [
            'name' => $this->name,
            'handle' => $this->handle,
            'color' => $this->color,
            'description' => $this->description,
            'sortOrder' => $this->sortOrder ?? 99,
            'default' => $this->default,
            'emails' => !empty($emails) ? array_combine($emails, $emails) : [],
            'store' => $this->getStore()->uid,
        ];
    }
}
