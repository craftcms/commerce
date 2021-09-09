<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\LineItem;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Purchasable as PurchasableRecord;
use craft\validators\UniqueValidator;

/**
 * Base Purchasable
 *
 * @property string $description the element's title or any additional descriptive information
 * @property bool $isAvailable whether the purchasable is currently available for purchase
 * @property bool $isPromotable whether this purchasable can be subject to discounts or sales
 * @property bool $onSale
 * @property float $promotionRelationSource The source for any promotion category relation
 * @property float $price the base price the item will be added to the line item with
 * @property-read float $salePrice the base price the item will be added to the line item with
 * @property-read Sale[] $sales sales models which are currently affecting the salePrice of this purchasable
 * @property int $shippingCategoryId the purchasable's shipping category ID
 * @property string $sku a unique code as per the commerce_purchasables table
 * @property array $snapshot
 * @property bool $isShippable
 * @property bool $isTaxable
 * @property int $taxCategoryId the purchasable's tax category ID
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
abstract class Purchasable extends Element implements PurchasableInterface
{
    /**
     * @var float|null
     */
    private $_salePrice;

    /**
     * @var Sale[]|null
     */
    private $_sales;

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        $names = parent::attributes();

        $names[] = 'isAvailable';
        $names[] = 'isPromotable';
        $names[] = 'price';
        $names[] = 'shippingCategoryId';
        $names[] = 'sku';
        $names[] = 'taxCategoryId';
        return $names;
    }

    /**
     * @inheritdoc
     * @since 3.2.9
     */
    public function fields()
    {
        $fields = parent::fields();

        $fields['salePrice'] = 'salePrice';
        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function extraFields()
    {
        $names = parent::extraFields();

        $names[] = 'description';
        $names[] = 'sales';
        $names[] = 'snapshot';
        return $names;
    }

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
    public function getSnapshot(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getSalePrice(): float
    {
        $this->_loadSales();

        return $this->_salePrice;
    }

    /**
     * Returns an array of sales models which are currently affecting the salePrice of this purchasable.
     *
     * @return Sale[]|null
     */
    public function getSales()
    {
        $this->_loadSales();

        return $this->_sales;
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
    public function getDescription(): string
    {
        return (string)$this;
    }

    /**
     * @inheritdoc
     */
    public function populateLineItem(LineItem $lineItem)
    {
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
    public function getIsAvailable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [
                ['sku'],
                UniqueValidator::class,
                'targetClass' => PurchasableRecord::class,
                'caseInsensitive' => true,
                'on' => self::SCENARIO_LIVE,
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function afterOrderComplete(Order $order, LineItem $lineItem)
    {
    }

    /**
     * @inheritdoc
     */
    public function hasFreeShipping(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function getIsShippable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getIsTaxable(): bool
    {
        return true;
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
     */
    public function getPromotionRelationSource()
    {
        return $this->id;
    }

    /**
     * Update purchasable table
     *
     * @param bool $isNew
     */
    public function afterSave(bool $isNew)
    {
        $purchasable = PurchasableRecord::findOne($this->id);

        if (!$purchasable) {
            $purchasable = new PurchasableRecord();
        }

        $purchasable->sku = $this->getSku();
        $purchasable->price = $this->getPrice();
        $purchasable->id = $this->id;

        // Only update the description for the primary site until we have a concept
        // of an order having a site ID
        if ($this->siteId == Craft::$app->getSites()->getPrimarySite()->id) {
            $purchasable->description = $this->getDescription();
        }

        $purchasable->save(false);

        parent::afterSave($isNew);
    }

    /**
     * Clean up purchasable table
     */
    public function afterDelete()
    {
        $purchasable = PurchasableRecord::findOne($this->id);

        if ($purchasable) {
            $purchasable->delete();
        }

        parent::afterDelete();
    }

    /**
     * @return Sale[] The sales that relate directly to this purchasable
     */
    public function relatedSales(): array
    {
        return Plugin::getInstance()->getSales()->getSalesRelatedToPurchasable($this);
    }

    /**
     * @return bool
     */
    public function getOnSale(): bool
    {
        return null === $this->salePrice ? false : ($this->salePrice != $this->price);
    }

    /**
     * Reloads any sales applicable to the purchasable for the current user.
     */
    private function _loadSales()
    {
        if (null === $this->_sales) {
            // Default the sales and salePrice to the original price without any sales
            $this->_sales = [];
            $this->_salePrice = $this->getPrice();

            if ($this->getId()) {
                $this->_sales = Plugin::getInstance()->getSales()->getSalesForPurchasable($this);
                $this->_salePrice = Plugin::getInstance()->getSales()->getSalePriceForPurchasable($this);
            }
        }
    }
}
