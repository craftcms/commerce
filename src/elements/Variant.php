<?php

namespace craft\commerce\elements;

use Craft;
use craft\commerce\base\Element;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\elements\db\ElementQueryInterface;

/**
 * Variant Model
 *
 * @property \craft\commerce\elements\Product $product
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class Variant extends Element
{
    // Properties
    // =========================================================================

    /**
     * @var int $id
     */
    public $id;

    /**
     * @var int $productId
     */
    public $productId;

    /**
     * @var int $isDefault
     */
    public $isDefault;

    /**
     * @var int $sku
     */
    public $sku;

    /**
     * @var int $price
     */
    public $price;

    /**
     * @var int $sortOrder
     */
    public $sortOrder;

    /**
     * @var int $width
     */
    public $width;

    /**
     * @var int $height
     */
    public $height;

    /**
     * @var int $length
     */
    public $length;

    /**
     * @var int $weight
     */
    public $weight;

    /**
     * @var int $stock
     */
    public $stock;

    /**
     * @var int $unlimitedStock
     */
    public $unlimitedStock;

    /**
     * @var int $minQty
     */
    public $minQty;

    /**
     * @var int $maxQty
     */
    public $maxQty;

    /**
     * @var
     */
    private $_salePrice;

    /**
     * @var
     */
    private $_salesApplied;

    /**
     * @var \craft\commerce\elements\Product The product that this variant is associated with.
     * @see getProduct()
     * @see setProduct()
     */
    private $_product;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();

        $rules[] = [['sku'], 'string', 'required'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        $fields = parent::fields();

        $fields['salePrice'] = function() {
            return $this->getSalePrice();
        };

        $fields['description'] = function() {
            return $this->getDescription();
        };

        return $fields;
    }

    /**
     * Getter provides opportunity to populate the salePrice if sales have not already been applied.
     *
     * @return null|float
     */
    public function getSalePrice()
    {
        if ($this->getSalesApplied() === null) {
            Plugin::getInstance()->getVariants()->applySales([$this], $this->getProduct());
        }

        return $this->_salePrice;
    }

    /**
     * @param $value
     */
    public function setSalePrice($value)
    {
        $this->_salePrice = $value;
    }

    /**
     * An array of sales models which are currently affecting the salePrice of this purchasable.
     *
     * @return \craft\commerce\base\SaleInterface[]
     */
    public function getSalesApplied()
    {
        return $this->_salesApplied;
    }

    /**
     * sets an array of sales models which are currently affecting the salePrice of this purchasable.
     *
     * @param \craft\commerce\base\SaleInterface[] $sales
     */
    public function setSalesApplied($sales)
    {
        $this->_salesApplied = $sales;
    }

    /**
     * Returns the product associated with this variant.
     *
     * @return Product|null The product associated with this variant, or null if it isn’t known
     */
    public function getProduct()
    {
        if ($this->_product === null) {
            if ($this->productId) {
                $this->_product = Plugin::getInstance()->getProducts()->getProductById($this->productId);
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
     * @param Product|null $product The product associated with this variant
     *
     * @return void
     */
    public function setProduct(Product $product = null)
    {
        $this->_product = $product;

        if ($product !== null) {
            $this->siteId = $product->siteId;

            if ($product->id) {
                $this->productId = $product->id;
            }
        }
    }

    /**
     * Returns the product title and variants title together for variable products.
     *
     * @return string
     */
    public function getDescription()
    {
        $format = $this->getProduct()->getType()->descriptionFormat;

        if ($format) {
            return Craft::$app->templates->renderObjectTemplate($format, $this);
        }

        return $this->getTitle();
    }

    /**
     * If the product's type has no variants, return the products title.
     *
     * @return string
     */
    public function getTitle()
    {
        if (!$this->getProduct()->getType()->hasVariants) {
            return $this->getProduct()->title;
        }

        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        $labels = parent::attributeLabels();

        return array_merge($labels, ['sku' => 'SKU']);
    }

    /**
     * @return bool
     */
    public function isEditable()
    {
        $product = $this->getProduct();

        if ($product) {
            return $product->isEditable();
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function __toString(): string
    {
        return (string) $this->getContent()->title;
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
     * @return \craft\models\FieldLayout|null
     */
    public function getFieldLayout()
    {
        if (($product = $this->getProduct()) !== null) {
            return $product->getType()->getVariantFieldLayout();
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
        return $this->price;
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
        return null === $this->salePrice ? false : ($this->salePrice != $this->price);
    }

    /**
     * We need to be explicit to meet interface
     *
     * @return string
     */
    public function getSku()
    {
        return $this->sku;
    }

    /**
     * We need to be explicit to meet interface
     *
     * @return int
     */
    public function getPurchasableId()
    {
        return $this->id;
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
     * Does this variants product has free shipping set.
     *
     * @return bool
     */
    public function hasFreeShipping()
    {
        return $this->getProduct()->freeShipping;
    }

    /**
     * Validate based on min and max qty and stock levels.
     *
     * @param \craft\commerce\models\LineItem $lineItem
     *
     * @return null
     */
    public function validateLineItem(LineItem $lineItem)
    {
        if (!$lineItem->qty) {
            return;
        }

        if ($lineItem->purchasable->getStatus() != Element::STATUS_ENABLED) {
            $lineItem->addError('purchasableId', Craft::t('commerce', 'commerce', 'Not enabled for sale.'));
        }

        $order = Plugin::getInstance()->getOrders()->getOrderById($lineItem->orderId);

        if ($order) {
            $qty = [];
            foreach ($order->getLineItems() as $item) {
                if (!isset($qty[$item->purchasableId])) {
                    $qty[$item->purchasableId] = 0;
                }
                if ($item->id == $lineItem->id) {
                    $qty[$item->purchasableId] += $lineItem->qty;
                } else {
                    $qty[$item->purchasableId] += $item->qty;
                }
            }

            if (!isset($qty[$lineItem->purchasableId])) {
                $qty[$lineItem->purchasableId] = $lineItem->qty;
            }

            if (!$this->unlimitedStock && $qty[$lineItem->purchasableId] > $this->stock) {
                $error = Craft::t('commerce', 'commerce', 'There are only {num} "{description}" items left in stock', ['num' => $this->stock, 'description' => $lineItem->purchasable->getDescription()]);
                $lineItem->addError('qty', $error);
            }

            if ($lineItem->qty < $this->minQty) {
                $error = Craft::t('commerce', 'commerce', 'Minimum order quantity for this item is {num}', ['num' => $this->minQty]);
                $lineItem->addError('qty', $error);
            }

            if ($this->maxQty != 0) {
                if ($lineItem->qty > $this->maxQty) {
                    $error = Craft::t('commerce', 'commerce', 'Maximum order quantity for this item is {num}', ['num' => $this->maxQty]);
                    $lineItem->addError('qty', $error);
                }
            }
        }
    }

    /**
     * @inheritdoc
     *
     * @return VariantQuery The newly created [[VariantQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new VariantQuery(static::class);
    }


    /**
     * @param \craft\commerce\models\LineItem $lineItem
     *
     * @return null
     */
    public function populateLineItem(LineItem $lineItem)
    {
        // Since we do not have a proper stock reservation system, we need deduct stock if they have more in the cart than is available, and to do this quietly.
        // If this occurs in the payment request, the user will be notified the order has changed.
        if (($lineItem->qty > $this->stock) && !$this->unlimitedStock) {
            $lineItem->qty = $this->stock;
        }

        $lineItem->weight = $this->weight * 1; //converting nulls
        $lineItem->height = $this->height * 1; //converting nulls
        $lineItem->length = $this->length * 1; //converting nulls
        $lineItem->width = $this->width * 1; //converting nulls

        $sales = Plugin::getInstance()->getSales()->getSalesForVariant($this);

        foreach ($sales as $sale) {
            $lineItem->saleAmount += $sale->calculateTakeoff($lineItem->price);
        }

        // Don't let sale amount be more than the price.
        if (-$lineItem->saleAmount > $lineItem->price) {
            $lineItem->saleAmount = -$lineItem->price;
        }

        // If the product is not promotable but has saleAmount, reset saleAmount to zero
        if (!$this->getIsPromotable()) {
            $lineItem->saleAmount = 0;
        }
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
     * Is this variant still available for purchase?
     *
     * @return bool
     */
    public function getIsAvailable()
    {
        // remove the item from the cart if the product is not enabled
        if ($this->getStatus() != Element::STATUS_ENABLED) {
            return false;
        }

        if ($this->stock < 1 && !$this->unlimitedStock) {
            return false;
        }

        return true;
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
        if ($productStatus != \craft\commerce\elements\Product::LIVE) {
            return Element::STATUS_DISABLED;
        }

        return $status;
    }

    /**
     * Sets some eager loaded elements on a given handle.
     *
     * @param string                $handle   The handle to load the elements with in the future
     * @param \craft\base\Element[] $elements The eager-loaded elements
     */
    public function setEagerLoadedElements(string $handle, array $elements)
    {
        if ($handle == 'product') {
            $product = isset($elements[0]) ? $elements[0] : null;
            $this->setProduct($product);
        } else {
            parent::setEagerLoadedElements($handle, $elements);
        }
    }

    // Original Element methods:


    /**
     * @return null|string
     */
    public function getName()
    {
        return Craft::t('commerce', 'Variants');
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @param null $context
     *
     * @return array
     */
    public function getSources($context = null)
    {
        $sources = [

            '*' => [
                'label' => Craft::t('commerce', 'All product\'s variants'),
            ]
        ];

        return $sources;
    }

    /**
     * @inheritdoc
     */
    public static function defineTableAttributes($source = null): array
    {
        return [
            'title' => Craft::t('commerce', 'Title'),
            'sku' => Craft::t('commerce', 'SKU'),
            'price' => Craft::t('commerce', 'Price'),
            'width' => Craft::t('commerce', 'Width ({unit})', ['unit' => Plugin::getInstance()->getSettings()->getSettings()->dimensionUnits]),
            'height' => Craft::t('commerce', 'Height ({unit})', ['unit' => Plugin::getInstance()->getSettings()->getSettings()->dimensionUnits]),
            'length' => Craft::t('commerce', 'Length ({unit})', ['unit' => Plugin::getInstance()->getSettings()->getSettings()->dimensionUnits]),
            'weight' => Craft::t('commerce', 'Weight ({unit})', ['unit' => Plugin::getInstance()->getSettings()->getSettings()->weightUnits]),
            'stock' => Craft::t('commerce', 'Stock'),
            'minQty' => Craft::t('commerce', 'Quantities')
        ];
    }

    /**
     * @return array
     */
    public static function defineSearchableAttributes(): array
    {
        return ['sku', 'price', 'width', 'height', 'length', 'weight', 'stock', 'unlimitedStock', 'minQty', 'maxQty'];
    }

    /**
     * @return array
     */
    public function defineCriteriaAttributes()
    {
        return [
            'sku' => AttributeType::Mixed,
            'product' => AttributeType::Mixed,
            'productId' => AttributeType::Mixed,
            'isDefault' => AttributeType::Mixed,
            'default' => AttributeType::Mixed,
            'stock' => AttributeType::Mixed,
            'hasStock' => AttributeType::Mixed,
            'order' => [AttributeType::String, 'default' => 'variants.sortOrder asc'],
        ];
    }


    /**
     * Sets the product on the resulting variants.
     *
     * @param Event $event
     *
     * @return void
     */
    public function setProductOnVariant(Event $event)
    {
        /** @var ElementCriteriaModel $criteria */
        $criteria = $event->sender;

        /** @var Variant[] $variants */
        $variants = $event->params['elements'];

        if ($criteria->product instanceof Product) {
            Plugin::getInstance()->getVariants()->setProductOnVariants($criteria->product, $variants);
        }
    }

    /**
     * @param array $row
     *
     * @return BaseModel
     */
    public function populateElementModel($row)
    {
        return new Variant($row);
    }

    /**
     * @param BaseElementModel $element
     * @param array            $params
     *
     * @return bool
     * @throws HttpException
     * @throws \Exception
     */
    public function saveElement(BaseElementModel $element, $params)
    {
        return Plugin::getInstance()->getVariants()->saveVariant($element);
    }

}
