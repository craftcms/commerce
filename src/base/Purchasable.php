<?php

namespace craft\commerce\base;

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
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getPurchasableId(): int
    {
        throw new NotImplementedException();
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
        throw new NotImplementedException();
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
}
