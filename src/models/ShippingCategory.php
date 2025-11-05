<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\base\Chippable;
use craft\base\Colorable;
use craft\base\Iconic;
use craft\commerce\base\HasStoreInterface;
use craft\commerce\base\Model;
use craft\commerce\base\StoreTrait;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingCategory as ShippingCategoryRecord;
use craft\enums\Color;
use craft\helpers\ArrayHelper;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * Shipping Category model.
 *
 * @property array|ProductType[] $productTypes
 * @property-read int[] $productTypeIds
 * @property string $cpEditUrl
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingCategory extends Model implements HasStoreInterface, Chippable, Colorable, Iconic
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
     * @var string|null Icon
     */
    public ?string $icon = null;

    /**
     * @var string|null Color
     */
    public ?string $color = null;

    /**
     * @var string|null Description
     */
    public ?string $description = null;

    /**
     * @var bool Default
     */
    public bool $default = false;

    /**
     * @var ProductType[]|null
     */
    private ?array $_productTypes = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var DateTime|null Date deleted
     * @since 4.2.0.1
     */
    public ?DateTime $dateDeleted = null;

    /**
     * Returns the name of this shipping category.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->name;
    }

    public function getCpEditUrl(): string
    {
        return $this->getStore()->getStoreSettingsUrl('shippingcategories/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    public static function get(int|string $id): ?static
    {
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($id);
    }

    /**
     * @inheritdoc
     */
    public function getUiLabel(): string
    {
        return Craft::t('site', $this->name);
    }

    /**
     * @inheritdoc
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @inheritdoc
     */
    public function getColor(): ?Color
    {
        return $this->color ? Color::tryFrom($this->color) : null;
    }

    /**
     * @param ProductType[] $productTypes
     */
    public function setProductTypes(array $productTypes): void
    {
        $this->_productTypes = $productTypes;
    }

    /**
     * @return ProductType[]
     * @throws InvalidConfigException
     */
    public function getProductTypes(): array
    {
        if (!isset($this->_productTypes) && $this->id) {
            $this->_productTypes = Plugin::getInstance()->getProductTypes()->getProductTypesByShippingCategoryId($this->id);
        }

        return $this->_productTypes ?? [];
    }

    /**
     * Helper method to just get the product type IDs
     *
     * @return int[]
     * @throws InvalidConfigException
     */
    public function getProductTypeIds(): array
    {
        return ArrayHelper::getColumn($this->getProductTypes(), 'id', false);
    }

    protected function defineRules(): array
    {
        return [
            [['name', 'handle'], 'required'],
            [['handle'],
                UniqueValidator::class,
                'targetClass' => ShippingCategoryRecord::class,
                'targetAttribute' => ['handle', 'storeId'],
                'message' => '{attribute} "{value}" has already been taken.',
            ],
            [['handle'], HandleValidator::class],
            [[
                'dateCreated',
                'dateDeleted',
                'dateUpdated',
                'default',
                'description',
                'handle',
                'icon',
                'color',
                'id',
                'name',
                'storeId',
            ], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'productTypes';
        $fields[] = 'productTypeIds';

        return $fields;
    }
}
