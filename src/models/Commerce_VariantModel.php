<?php
namespace Craft;

use Commerce\Interfaces\Purchasable;

/**
 * Class Commerce_VariantModel
 *
 * @property int $id
 * @property int $productId
 * @property string $sku
 * @property bool $isDefault
 * @property float $price
 * @property int $sortOrder
 * @property float $width
 * @property float $height
 * @property float $length
 * @property float $weight
 * @property int $stock
 * @property bool $unlimitedStock
 * @property int $minQty
 * @property int $maxQty
 *
 * @property Commerce_ProductModel $product
 *
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_VariantModel extends BaseElementModel implements Purchasable
{
    // Properties
    // =========================================================================

    /**
     * @var
     */
    public $salePrice;

    /**
     * @var string
     */
    protected $elementType = 'Commerce_Variant';

    /**
     * @var Commerce_ProductModel The product that this variant is associated with.
     * @see getProduct()
     * @see setProduct()
     */
    private $_product;

    // Public Methods
    // =========================================================================

    /**
     * @return bool
     */
    public function isEditable()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isLocalized()
    {
        return false;
    }

    /**
     * @return mixed
     */
    public function __toString()
    {
        return $this->getContent()->title;
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/products/' . $this->product->type->handle . '/' . $this->product->id . '/variants/' . $this->id);
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->product->url . '?variant=' . $this->id;
    }

    /**
     * @return FieldLayoutModel|null
     */
    public function getFieldLayout()
    {
        if (($product = $this->getProduct()) !== null) {
            return $product->getType()->asa('variantFieldLayout')->getFieldLayout();
        }

        return null;
    }

    /**
     * We need to be explicit to meet interface
     *
     * @return mixed
     */
    public function getPrice()
    {
        return $this->getAttribute('price');
    }

    /**
     * We need to be explicit to meet interface
     *
     * @return string
     */
    public function getSnapshot()
    {
        $data = [
            'onSale' => $this->getOnSale(),
            'cpEditUrl' => $this->getProduct() ? $this->getProduct()->getCpEditUrl() : ''
        ];

        $data['product'] = $this->getProduct() ? $this->getProduct()->getSnapshot() : '';

        return array_merge($this->getAttributes(), $data);
    }

    /**
     * @return bool
     */
    public function getOnSale()
    {
        return is_null($this->salePrice) ? false : ($this->salePrice != $this->price);
    }

    /**
     * Returns the product associated with this variant.
     *
     * @return Commerce_ProductModel|null The product associated with this variant, or null if it isn’t known
     */
    public function getProduct()
    {
        if ($this->_product === null) {
            if ($this->productId) {
                $this->_product = craft()->commerce_products->getProductById($this->productId);
            }
            if ($this->_product === null) {
                $this->_product = false;
            }
        }

        if ($this->_product !== false) {
            return $this->_product;
        }

        return null;
    }

    /**
     * Sets the product associated with this variant.
     *
     * @param Commerce_ProductModel $product The product associated with this variant
     *
     * @return void
     */
    public function setProduct(Commerce_ProductModel $product)
    {
        $this->_product = $product;
        $this->locale = $product->locale;
        if ($product->id) {
            $this->productId = $product->id;
        }
    }

    /**
     * We need to be explicit to meet interface
     *
     * @return string
     */
    public function getSku()
    {
        return $this->getAttribute('sku');
    }

    /**
     * We need to be explicit to meet interface
     *
     * @return string
     */
    public function getDescription()
    {
        if ($this->getProduct() && $this->getProduct()->getType()->hasVariants) {
            return $this->getProduct()->getTitle();
        }

        return $this->getTitle();
    }

    /**
     * We need to be explicit to meet interface
     *
     * @return int
     */
    public function getPurchasableId()
    {
        return $this->getAttribute('id');
    }

    /**
     * Does this variants product has free shipping set.
     *
     * @return bool
     */
    public function hasFreeShipping()
    {
        return $this->product->freeShipping;
    }

    /**
     * Validate based on min and max qty and stock levels.
     *
     * @param Commerce_LineItemModel $lineItem
     *
     * @return mixed
     */
    public function validateLineItem(Commerce_LineItemModel $lineItem)
    {

        if (!$this->unlimitedStock && $lineItem->qty > $this->stock) {
            $error = sprintf('There are only %d items left in stock',
                $this->stock);
            $lineItem->addError('qty', $error);
        }

        if ($lineItem->qty < $this->minQty) {
            $error = sprintf('Minimum order quantity for this item is %d',
                $this->minQty);
            $lineItem->addError('qty', $error);
        }

        if ($this->maxQty != 0) {
            if ($lineItem->qty > $this->maxQty) {
                $error = sprintf('Maximum order quantity for this item is %d',
                    $this->maxQty);
                $lineItem->addError('qty', $error);
            }
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), [
            'id' => [AttributeType::Number],
            'productId' => [AttributeType::Number],
            'isDefault' => [AttributeType::Bool],
            'sku' => [AttributeType::String, 'required' => true, 'label' => 'SKU'],
            'price' => [
                AttributeType::Number,
                'decimals' => 4,
                'required' => true
            ],
            'sortOrder' => AttributeType::Number,
            'width' => [AttributeType::Number, 'decimals' => 4],
            'height' => [AttributeType::Number, 'decimals' => 4],
            'length' => [AttributeType::Number, 'decimals' => 4],
            'weight' => [AttributeType::Number, 'decimals' => 4],
            'stock' => [AttributeType::Number],
            'unlimitedStock' => [AttributeType::Bool, 'default' => 1],
            'minQty' => [AttributeType::Number],
            'maxQty' => [AttributeType::Number]
        ]);
    }

}
