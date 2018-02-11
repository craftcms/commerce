<?php

namespace craft\commerce\elements;

use Craft;
use craft\commerce\base\Element;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\helpers\Currency;
use craft\commerce\models\LineItem;
use craft\commerce\models\ProductType;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Variant as VariantRecord;
use craft\db\Query;
use craft\elements\db\ElementQueryInterface;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Variant Model
 *
 * @property Sale[]            $salesApplied
 * @property bool              $onSale
 * @property string            $name
 * @property null|array|Sale[] $sales
 * @property null|float        $salePrice
 * @property string            $eagerLoadedElements
 * @property Product           $product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Variant extends Purchasable
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public $sku;

    /**
     * @inheritdoc
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
     * @var Product The product that this variant is associated with.
     * @see getProduct()
     * @see setProduct()
     */
    private $_product;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Product Variant');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'variant';
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return parent::__toString();
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = [['sku'], 'string'];
        $rules[] = [['sku', 'price'], 'required'];

        if (!$this->unlimitedStock) {
            $rules[] = [['stock'], 'required'];
        }

        return $rules;
    }

    /**
     * An array of sales models which are currently affecting the salePrice of this purchasable.
     *
     * @return Sale[]
     *
     * @deprecated
     */
    public function getSalesApplied(): array
    {
        Craft::$app->getDeprecator()->log('getSalesApplied()', 'The getSalesApplied() function has been deprecated. Use getSales() instead.');

        return $this->getSales();
    }

    /**
     * sets an array of sales models which are currently affecting the salePrice of this purchasable.
     *
     * @param Sale[] $sales
     *
     * @deprecated
     */
    public function setSalesApplied($sales)
    {
        Craft::$app->getDeprecator()->log('setSalesApplied()', 'The setSalesApplied() function has been deprecated. Use setSales() instead.');

        $this->setSales($sales);
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
    public function getDescription(): string
    {
        $format = $this->getProduct()->getType()->descriptionFormat;

        if ($format) {
            return Craft::$app->getView()->renderObjectTemplate($format, $this);
        }

        return $this->getTitle();
    }

    /**
     * If the product's type has no variants, return the products title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        if (!$this->getProduct()->getType()->hasVariants) {
            return $this->getProduct()->title;
        }

        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        $labels = parent::attributeLabels();

        return array_merge($labels, ['sku' => 'SKU']);
    }

    /**
     * @return bool
     */
    public function isEditable(): bool
    {
        $product = $this->getProduct();

        if ($product) {
            return $product->isEditable();
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): string
    {
        return $this->getProduct() ? $this->getProduct()->getCpEditUrl() : null;
    }

    /**
     * @inheritdoc
     */
    public function getUrl(): string
    {
        return $this->product->url.'?variant='.$this->id;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        if (($product = $this->getProduct()) !== null) {
            return $product->getType()->getVariantFieldLayout();
        }

        return null;
    }

    /**
     * Cache on the purchasable table
     *
     * @inheritdoc
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * We need to be explicit to meet interface
     *
     * @return array
     */
    public function getSnapshot(): array
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
    public function getOnSale(): bool
    {
        return null === $this->salePrice ? false : (Currency::round($this->salePrice) != Currency::round($this->price));
    }

    /**
     * @inheritdoc
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * @inheritdoc
     */
    public function getPurchasableId(): int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getTaxCategoryId(): int
    {
        return $this->getProduct()->taxCategoryId;
    }

    /**
     * @inheritdoc
     */
    public function getShippingCategoryId(): int
    {
        return $this->getProduct()->shippingCategoryId;
    }

    /**
     * Does this variant have stock?
     *
     * @return bool
     */
    public function hasStock(): bool
    {
        return $this->stock > 0 || $this->unlimitedStock;
    }

    /**
     * @inheritdoc
     */
    public function hasFreeShipping(): bool
    {
        return (bool)$this->getProduct()->freeShipping;
    }

    /**
     * @inheritdoc
     */
    public function validateLineItem(LineItem $lineItem)
    {
        if (!$lineItem->qty) {
            return;
        }

        if ($lineItem->purchasable->getStatus() != Element::STATUS_ENABLED) {
            $lineItem->addError('purchasableId', Craft::t('commerce', 'Not enabled for sale.'));
        }

        $order = Plugin::getInstance()->getOrders()->getOrderById($lineItem->orderId);

        if ($order) {
            $qty = [];
            foreach ($order->getLineItems() as $item) {
                if (!isset($qty[$item->purchasableId])) {
                    $qty[$item->purchasableId] = 0;
                }

                // count new line items
                if ($lineItem->id === null) {
                    $qty[$item->purchasableId] = $lineItem->qty;
                }

                if ($item->id == $lineItem->id) {
                    $qty[$item->purchasableId] += $lineItem->qty;
                } else {
                    // count other line items with same purchasableId
                    $qty[$item->purchasableId] += $item->qty;
                }
            }

            if (!isset($qty[$lineItem->purchasableId])) {
                $qty[$lineItem->purchasableId] = $lineItem->qty;
            }

            if (!$this->unlimitedStock && $qty[$lineItem->purchasableId] > $this->stock) {
                $error = Craft::t('commerce', 'There are only {num} "{description}" items left in stock', ['num' => $this->stock, 'description' => $lineItem->purchasable->getDescription()]);
                $lineItem->addError('qty', $error);
            }

            if ($lineItem->qty < $this->minQty) {
                $error = Craft::t('commerce', 'Minimum order quantity for this item is {num}', ['num' => $this->minQty]);
                $lineItem->addError('qty', $error);
            }

            if ($this->maxQty != 0 && $lineItem->qty > $this->maxQty) {
                $error = Craft::t('commerce', 'Maximum order quantity for this item is {num}', ['num' => $this->maxQty]);
                $lineItem->addError('qty', $error);
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
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array
    {
        if ($handle == 'product') {
            // Get the source element IDs
            $sourceElementIds = [];

            foreach ($sourceElements as $sourceElement) {
                $sourceElementIds[] = $sourceElement->id;
            }

            $map = (new Query())
                ->select('id as source, productId as target')
                ->from('commerce_variants')
                ->where(['in', 'id', $sourceElementIds])
                ->all();

            return [
                'elementType' => Product::class,
                'map' => $map
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     */
    public function populateLineItem(LineItem $lineItem)
    {
        // Since we do not have a proper stock reservation system, we need deduct stock if they have more in the cart than is available, and to do this quietly.
        // If this occurs in the payment request, the user will be notified the order has changed.
        if (($lineItem->qty > $this->stock) && !$this->unlimitedStock) {
            $lineItem->qty = $this->stock;
        }

        $lineItem->weight = (float)$this->weight; //converting nulls
        $lineItem->height = (float)$this->height; //converting nulls
        $lineItem->length = (float)$this->length; //converting nulls
        $lineItem->width = (float)$this->width; //converting nulls

        return null;
    }

    /**
     * A promotion category is related to this element if the category is related to the product OR the variant.
     *
     * @return array
     */
    public function getPromotionRelationSource(): array
    {
        return [$this->id, $this->getProduct()->id];
    }

    /**
     * @inheritdoc
     */
    public function getIsPromotable(): bool
    {
        return (bool)$this->getProduct()->promotable;
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        if (!$isNew) {
            $record = VariantRecord::findOne($this->id);

            if (!$record) {
                throw new Exception('Invalid variant ID: '.$this->id);
            }
        } else {
            $record = new VariantRecord();
            $record->id = $this->id;
        }

        $record->productId = $this->productId;
        $record->sku = $this->sku;
        $record->price = $this->price;
        $record->width = $this->width;
        $record->height = $this->height;
        $record->length = $this->length;
        $record->weight = $this->weight;
        $record->minQty = $this->minQty;
        $record->maxQty = $this->maxQty;
        $record->stock = $this->stock;
        $record->isDefault = $this->isDefault;
        $record->sortOrder = $this->sortOrder;
        $record->unlimitedStock = $this->unlimitedStock;

        if (!$this->getProduct()->getType()->hasDimensions) {
            $record->width = $this->width = 0;
            $record->height = $this->height = 0;
            $record->length = $this->length = 0;
            $record->weight = $this->weight = 0;
        }

        $record->save();

        return parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function getIsAvailable(): bool
    {
        if (!parent::getIsAvailable()) {
            return false;
        }

        return $this->stock >= 1 || $this->unlimitedStock;
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        $status = parent::getStatus();

        $productStatus = $this->getProduct()->getStatus();
        if ($productStatus != Product::STATUS_LIVE) {
            return Element::STATUS_DISABLED;
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    public function setEagerLoadedElements(string $handle, array $elements)
    {
        if ($handle == 'product') {
            $product = $elements[0] ?? null;
            $this->setProduct($product);
        } else {
            parent::setEagerLoadedElements($handle, $elements);
        }
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
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        $productType = $this->getProduct()->getType() ?? null;
        $product = $this->getProduct();

        if ($productType === null) {
            throw new InvalidConfigException('Variant is missing its product type ID');
        }

        if ($productType === null) {
            throw new InvalidConfigException('Variant is missing its product ID');
        }

        // Use the product type's titleFormat if the title field is not shown
        if (!$productType->hasVariantTitleField && $productType->hasVariants) {
            try {
                $this->title = Craft::$app->getView()->renderObjectTemplate($productType->titleFormat, $this);
            } catch (\Exception $e) {
                $this->title = '';
            }
        }

        if (!$productType->hasVariants) {
            // Since Variant::getTitle() returns the parent products title when the product has
            // no variants, lets save the products title as the variant title anyway.
            $this->title = $product->title;
        }

        // If we have a blank SKU, generate from product type's skuFormat
        if (!$this->sku) {
            try {
                $this->sku = Craft::$app->getView()->renderObjectTemplate($productType->skuFormat, $this);
            } catch (\Exception $e) {
                Craft::error('Craft Commerce could not generate the supplied SKU format: '.$e->getMessage(), __METHOD__);
                $this->sku = '';
            }
        }

        if ($this->unlimitedStock) {
            $this->stock = 0;
        }

        return parent::beforeValidate();
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
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
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => Craft::t('commerce', 'Title'),
            'sku' => Craft::t('commerce', 'SKU'),
            'price' => Craft::t('commerce', 'Price'),
            'width' => Craft::t('commerce', 'Width ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'height' => Craft::t('commerce', 'Height ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'length' => Craft::t('commerce', 'Length ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'weight' => Craft::t('commerce', 'Weight ({unit})', ['unit' => Plugin::getInstance()->getSettings()->weightUnits]),
            'stock' => Craft::t('commerce', 'Stock'),
            'minQty' => Craft::t('commerce', 'Quantities')
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        $attributes[] = 'title';
        $attributes[] = 'sku';
        $attributes[] = 'price';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['sku', 'price', 'width', 'height', 'length', 'weight', 'stock', 'unlimitedStock', 'minQty', 'maxQty'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('commerce', 'Title')
        ];
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        /* @var $productType ProductType */
        $productType = $this->product->getType();

        switch ($attribute) {
            case 'sku':
                {
                    return $this->sku;
                }
            case 'price':
                {
                    $code = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

                    return Craft::$app->getLocale()->getFormatter()->asCurrency($this->$attribute, strtoupper($code));
                }
            case 'weight':
                {
                    if ($productType->hasDimensions) {
                        return Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute).' '.Plugin::getInstance()->getSettings()->getSettings()->weightUnits;
                    }

                    return '';
                }
            case 'length':
            case 'width':
            case 'height':
                {
                    if ($productType->hasDimensions) {
                        return Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute).' '.Plugin::getInstance()->getSettings()->getSettings()->dimensionUnits;
                    }

                    return '';
                }
            default:
                {
                    return parent::tableAttributeHtml($attribute);
                }
        }
    }
}
