<?php

namespace craft\commerce\base;

use Craft;
use craft\commerce\errors\NotImplementedException;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;

/**
 * Base Purchasable
 *
 * @property bool   $isPromotable
 * @property bool   $isAvailable
 * @property int    $purchasableId
 * @property int    $shippingCategoryId
 * @property float  $price
 * @property string $description
 * @property string $sku
 * @property array  $snapshot
 * @property int    $taxCategoryId
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
abstract class Purchasable extends Element implements PurchasableInterface
{

    private $_salePrice;

    /**
     * @var
     */
    private $_sales;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $classNameParts = explode('\\', static::class);

        return array_pop($classNameParts);
    }


    /**
     * @inheritdoc
     */
    public function getPurchasableId(): int
    {
        throw new NotImplementedException('Purchasable needs a purchasable ID');
    }

    /**
     * @inheritdoc
     */
    public function getSnapshot(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getPrice(): float
    {
        throw new NotImplementedException('Purchasable needs a price');
    }

    /**
     * @inheritdoc
     */
    public function getLivePrice(): float
    {
        return $this->getPrice();
    }

    /**
     * Getter provides opportunity to populate the salePrice if sales have not already been applied.
     *
     * @return null|float
     */
    public function getSalePrice(): float
    {
        if ($this->getSales() === null) {
            Plugin::getInstance()->getSales()->applySales($this);
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
     * @return Sale[]|null
     */
    public function getSales()
    {
        return $this->_sales;
    }

    /**
     * sets an array of sales models which are currently affecting the salePrice of this purchasable.
     *
     * @param Sale[] $sales
     */
    public function setSales(array $sales)
    {
        $this->_sales = $sales;
    }

    /**
     * @inheritdoc
     */
    public function getSku(): string
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getTaxCategoryId(): int
    {
        return Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id;
    }

    /**
     * @inheritdoc
     */
    public function getShippingCategoryId(): int
    {
        return Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory()->id;
    }

    /**
     * @inheritdoc
     */
    public function getIsAvailable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function populateLineItem(LineItem $lineItem)
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getLineItemRules(LineItem $lineItem): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function hasFreeShipping(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getIsPromotable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     *
     * @return int|array
     */
    public function getPromotionRelationSource()
    {
        return $this->id;
    }
}
