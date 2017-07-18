<?php
namespace Craft;

use Commerce\Base\Purchasable as BasePurchasable;
use Commerce\Helpers\CommerceCurrencyHelper;

/**
 * Class Commerce_VariantModel
 *
 * @property int                   $id
 * @property int                   $productId
 * @property string                $sku
 * @property bool                  $isDefault
 * @property float                 $price
 * @property int                   $sortOrder
 * @property float                 $width
 * @property float                 $height
 * @property float                 $length
 * @property float                 $weight
 * @property int                   $stock
 * @property bool                  $unlimitedStock
 * @property int                   $minQty
 * @property int                   $maxQty
 *
 * @property Commerce_ProductModel $product
 *
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_VariantModel extends BasePurchasable
{
    // Properties
    // =========================================================================

    private $_salePrice;

    /**
     * @var
     */
    private $_salesApplied;

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
        $product = $this->getProduct();

        if ($product)
        {
            return $product->isEditable();
        }

        return false;
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
        return $this->getProduct() ? $this->getProduct()->getCpEditUrl() : null;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->product->url.'?variant='.$this->id;
    }

    /**
     * Returns the variant's status.
     *
     * @return string|null
     */
    public function getStatus()
    {
        $status = parent::getStatus();

        $productStatus = $this->getProduct()->getStatus();
        if ($productStatus != Commerce_ProductModel::LIVE)
        {
            return BaseElementModel::DISABLED;
        }

        return $status;
    }

    /**
     * @return FieldLayoutModel|null
     */
    public function getFieldLayout()
    {
        if (($product = $this->getProduct()) !== null)
        {
            return $product->getType()->asa('variantFieldLayout')->getFieldLayout();
        }

        return null;
    }

    /**
     * Getter provides opportunity to populate the salePrice if sales have not already been applied.
     *
     * @return null|float
     */
    public function getSalePrice()
    {
        if ($this->getSalesApplied() === null)
        {
            craft()->commerce_variants->applySales([$this], $this->getProduct());
        }

        return $this->_salePrice;
    }


    public function setSalePrice($value)
    {
        $this->_salePrice = $value;
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
            'onSale'    => $this->getOnSale(),
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
        return $this->salePrice === null ? false : (CommerceCurrencyHelper::round($this->salePrice) != CommerceCurrencyHelper::round($this->price));
    }

    /**
     * Returns whether this product is promotable.
     *
     * @return bool
     */
    public function getIsPromotable()
    {
        return $this->getProduct()->promotable;
    }

    /**
     * Returns the product associated with this variant.
     *
     * @return Commerce_ProductModel|null The product associated with this variant, or null if it isn’t known
     */
    public function getProduct()
    {
        if ($this->_product === null)
        {
            if ($this->productId)
            {
                $this->_product = craft()->commerce_products->getProductById($this->productId);
            }
            if ($this->_product === null)
            {
                $this->_product = false;
            }
        }

        if ($this->_product !== false)
        {
            return $this->_product;
        }

        return null;
    }

    /**
     * Sets the product associated with this variant.
     *
     * @param Commerce_ProductModel|null $product The product associated with this variant
     *
     * @return void
     */
    public function setProduct(Commerce_ProductModel $product = null)
    {
        $this->_product = $product;

        if ($product !== null) {
            $this->locale = $product->locale;

            if ($product->id) {
                $this->productId = $product->id;
            }
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
     * If the product's type has no variants, return the products title.
     *
     * @return string
     */
    public function getTitle()
    {
        if (!$this->getProduct()->getType()->hasVariants)
        {
            return $this->getProduct()->getTitle();
        }

        return parent::getTitle();
    }

    /**
     * Returns the product title and variants title together for variable products.
     *
     * @return string
     */
    public function getDescription()
    {
        $format = $this->getProduct()->getType()->descriptionFormat;

        if ($format)
        {
            return craft()->templates->renderObjectTemplate($format, $this);
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
     * Returns the products tax category
     *
     * @return int
     */
    public function getTaxCategoryId()
    {
        return $this->getProduct()->taxCategoryId;
    }

    /**
     * Returns the products shipping category
     *
     * @return int
     */
    public function getShippingCategoryId()
    {
        return $this->getProduct()->shippingCategoryId;
    }

    /**
     * Does this variants have stock?
     *
     * @return bool
     */
    public function hasStock()
    {
        return (bool)($this->stock > 0 || $this->unlimitedStock);
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
     * @return null
     */
    public function validateLineItem(Commerce_LineItemModel $lineItem)
    {
        if (!$lineItem->qty)
        {
            return;
        }

        if ($lineItem->purchasable->getStatus() != BaseElementModel::ENABLED)
        {
            $lineItem->addError('purchasableId',Craft::t('Not enabled for sale.'));
        }

        $order = craft()->commerce_orders->getOrderById($lineItem->orderId);

        if ($order)
        {
            $qty = [];
            foreach ($order->getLineItems() as $item)
            {
                if (!isset($qty[$item->purchasableId]))
                {
                    $qty[$item->purchasableId] = 0;
                }

                // count new line items
                if($lineItem->id === null)
                {
                    $qty[$item->purchasableId] += $lineItem->qty;
                }

                // count updated line items
                if ($item->id == $lineItem->id)
                {
                    $qty[$item->purchasableId] += $lineItem->qty;
                }
                else
                {
                    // count other line items with same purchasableId
                    $qty[$item->purchasableId] += $item->qty;
                }
            }

            if (!isset($qty[$lineItem->purchasableId]))
            {
                $qty[$lineItem->purchasableId] = $lineItem->qty;
            }

            if (!$this->unlimitedStock && $qty[$lineItem->purchasableId] > $this->stock)
            {
                $error = Craft::t('There are only {num} "{description}" items left in stock', ['num' => $this->stock, 'description' => $lineItem->purchasable->getDescription()]);
                $lineItem->addError('qty', $error);
            }

            if ($lineItem->qty < $this->minQty)
            {
                $error = Craft::t('Minimum order quantity for this item is {num}', ['num' => $this->minQty]);
                $lineItem->addError('qty', $error);
            }

            if ($this->maxQty != 0)
            {
                if ($lineItem->qty > $this->maxQty)
                {
                    $error = Craft::t('Maximum order quantity for this item is {num}', ['num' => $this->maxQty]);
                    $lineItem->addError('qty', $error);
                }
            }
        }
    }

    /**
     * sets an array of sales models which are currently affecting the salePrice of this purchasable.
     *
     * @param Commerce_SaleModel[] $sales
     */
    public function setSalesApplied($sales)
    {
        $this->_salesApplied = $sales;
    }

    /**
     * An array of sales models which are currently affecting the salePrice of this purchasable.
     *
     * @return Commerce_SaleModel[]
     */
    public function getSalesApplied()
    {
        return $this->_salesApplied;
    }

    /**
     * @param Commerce_LineItemModel $lineItem
     *
     * @return null
     */
    public function populateLineItem(Commerce_LineItemModel $lineItem)
    {
        // Since we do not have a proper stock reservation system, we need deduct stock if they have more in the cart than is available, and to do this quietly.
        // If this occurs in the payment request, the user will be notified the order has changed.
        if (($lineItem->qty > $this->stock) && !$this->unlimitedStock)
        {
            $lineItem->qty = $this->stock;
        }

        $lineItem->weight = (float) $this->weight;
        $lineItem->height = (float) $this->height;
        $lineItem->length = (float) $this->length;
        $lineItem->width = (float) $this->width;

        $sales = craft()->commerce_sales->getSalesForVariant($this);

        foreach ($sales as $sale)
        {
            $lineItem->saleAmount += $sale->calculateTakeoff($lineItem->price);
        }

        // Don't let sale amount be more than the price.
        if (-$lineItem->saleAmount > $lineItem->price)
        {
            $lineItem->saleAmount = -$lineItem->price;
        }

        // If the product is not promotable but has saleAmount, reset saleAmount to zero
        if (!$this->getIsPromotable())
        {
            $lineItem->saleAmount = 0;
        }
    }

    /**
     * Is this variant still available for purchase?
     *
     * @return bool
     */
    public function getIsAvailable()
    {
        // remove the item from the cart if the product is not enabled
        if ($this->getStatus() != BaseElementModel::ENABLED)
        {
            return false;
        }

        if ($this->stock < 1 && !$this->unlimitedStock)
        {
            return false;
        }

        return true;
    }

    /**
     * Sets some eager loaded elements on a given handle.
     *
     * @param string             $handle   The handle to load the elements with in the future
     * @param BaseElementModel[] $elements The eager-loaded elements
     */
    public function setEagerLoadedElements($handle, $elements)
    {
        if ($handle == 'product') {
            $product = isset($elements[0]) ? $elements[0] : null;
            $this->setProduct($product);
        } else {
            parent::setEagerLoadedElements($handle, $elements);
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
            'id'             => [AttributeType::Number],
            'productId'      => [AttributeType::Number],
            'isDefault'      => [AttributeType::Bool],
            'sku'            => [AttributeType::String, 'required' => true, 'label' => 'SKU'],
            'price'          => [
                AttributeType::Number,
                'decimals' => 4,
                'required' => true
            ],
            'sortOrder'      => AttributeType::Number,
            'width'          => [AttributeType::Number, 'decimals' => 4],
            'height'         => [AttributeType::Number, 'decimals' => 4],
            'length'         => [AttributeType::Number, 'decimals' => 4],
            'weight'         => [AttributeType::Number, 'decimals' => 4],
            'stock'          => [AttributeType::Number],
            'unlimitedStock' => [AttributeType::Bool, 'default' => 0],
            'minQty'         => [AttributeType::Number],
            'maxQty'         => [AttributeType::Number]
        ]);
    }

}
