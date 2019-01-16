<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements;

use Craft;
use craft\base\Element;
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
use yii\db\Expression;

/**
 * Donation purchasable.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Donation extends Purchasable
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $sku = 'DONATION';

    /**
     * @inheritdoc
     */
    public $price;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return Craft::t('commerce', 'Donation');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Donation');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'donation';
    }

    /**
     * Returns the product title and variants title together for variable products.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return Craft::t('commerce', 'Donation');
    }

    /**
     * @inheritdoc
     * @return bool
     */
    public function getIsEditable(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getUrl(): string
    {
        return '';
    }

    /**
     * Cached on the purchasable table.
     *
     * @inheritdoc
     */
    public function getPrice(): float
    {
        return 0;
    }

    /**
     *
     * @return array
     * @throws InvalidConfigException
     */
    public function getSnapshot(): array
    {
        return [];
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
     * Returns whether this variant has stock.
     *
     * @return bool
     */
    public function hasStock(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function hasFreeShipping(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     * @return VariantQuery The newly created [[VariantQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new VariantQuery(static::class);
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
    public function populateLineItem(LineItem $lineItem)
    {
        if (isset($lineItem->options['donationAmount'])) {
            $lineItem->salePrice = $lineItem->options['donationAmount'];
            $lineItem->saleAmount = 0;
        }

        return $lineItem->salePrice ?? 0;
    }

    /**
     * @inheritdoc
     */
    public function getIsPromotable(): bool
    {
        return false;
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
    public static function hasContent(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
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
        return false;
    }
}
