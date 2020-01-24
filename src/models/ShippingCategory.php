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

/**
 * Shipping Category model.
 *
 * @property string $cpEditUrl
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingCategory extends Model
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
     * @var string Description
     */
    public $description;

    /**
     * @var bool Default
     */
    public $default;

    /**
     * @var ProductType[]
     */
    private $_productTypes;

    // Public Methods
    // =========================================================================

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
     * @param array $productTypes
     */
    public function setProductTypes($productTypes)
    {
        $this->_productTypes = $productTypes;
    }

    /**
     * @return ProductType[]
     */
    public function getProductTypes(): array
    {
        if ($this->_productTypes === null) {
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
        $ids = [];
        foreach ($this->getProductTypes() as $productType) {
            $ids[] = $productType->id;
        }

        return $ids;
    }

    /**
     * @return array
     */
    public function rules()
    {
        $rules = parent::rules();

        $rules [] = [['name', 'handle'], 'required'];

        return $rules;
    }
}
