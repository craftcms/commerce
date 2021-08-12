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
     * @var string Name
     */
    public string $name;

    /**
     * @var string Handle
     */
    public string $handle;

    /**
     * @var string Description
     */
    public string $description;

    /**
     * @var bool Default
     */
    public bool $default;

    /**
     * @var ProductType[]
     */
    private array $_productTypes;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated;

    /**
     * Returns the name of this shipping category.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
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
     */
    public function getProductTypes(): array
    {
        if (!isset($this->_productTypes)) {
            $this->_productTypes = Plugin::getInstance()->getProductTypes()->getProductTypesByShippingCategoryId($this->id);
        }

        return $this->_productTypes;
    }

    /**
     * Helper method to just get the product type IDs
     *
     * @return int[]
     */
    public function getProductTypeIds(): array
    {
        return ArrayHelper::getColumn($this->getProductTypes(), 'id', false);
    }

    /**
     * @return array
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules [] = [['name', 'handle'], 'required'];

        return $rules;
    }
}
