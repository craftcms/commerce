<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\HasStoreInterface;
use craft\commerce\base\Model;
use craft\commerce\base\StoreTrait;
use craft\commerce\records\LineItemStatus as LineItemStatusRecord;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
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
class LineItemStatus extends Model implements HasStoreInterface
{
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

    protected function defineRules(): array
    {
        return [
            [['name', 'handle'], 'required'],
            [['handle'],
                UniqueValidator::class,
                'targetClass' => LineItemStatusRecord::class,
                'targetAttribute' => ['handle', 'storeId'],
                'filter' => ['isArchived' => false],
                'message' => '{attribute} "{value}" has already been taken.',
            ],
            [
                ['handle'],
                HandleValidator::class,
                'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title', 'create'],
            ],
            [[
                'id',
                'storeId',
                'name',
                'handle',
                'color',
                'sortOrder',
                'default',
                'isArchived',
                'dateArchived',
                'uid',
            ], 'safe'],
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

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/lineitemstatuses/' . $this->getStore()->handle . '/' . $this->id);
    }

    /**
     * @return string
     */
    public function getLabelHtml(): string
    {
        return Cp::statusLabelHtml([
            'label' => Html::encode($this->name),
            'color' => $this->color,
        ]);
    }

    /**
     * Returns the config for this status.
     *
     * @since 3.2.2
     */
    public function getConfig(): array
    {
        return [
            'store' => $this->getStore()->uid,
            'name' => $this->name,
            'handle' => $this->handle,
            'color' => $this->color,
            'sortOrder' => $this->sortOrder ?: 9999,
            'default' => $this->default,
        ];
    }
}
