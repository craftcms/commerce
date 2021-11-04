<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
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
class ShippingCategory extends Model
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
     * Returns the name of this shipping category.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->name;
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/shipping/shippingcategories/' . $this->id);
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
