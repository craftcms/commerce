<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Elements;

use craft\commerce\records\PurchasableStore as PurchasableStoreRecord;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Purchasable\Models\Donation as DonationRecord;
use CraftCms\Commerce\Purchasable\Queries\DonationQuery;
use CraftCms\Commerce\Purchasable\Validation\DonationRules;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Store\Models\Store;
use CraftCms\Commerce\Store\Stores;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use Override;
use yii\validators\Validator;
use function CraftCms\Cms\t;

#[Ruleset(DonationRules::class)]
class Donation extends Purchasable
{
    /**
     * By default, the donation is not available for purchase.
     */
    public bool $availableForPurchase = false;

    #[Override]
    public static function hasInventory(): bool
    {
        return false;
    }

    #[Override]
    public static function hasStatuses(): bool
    {
        return true;
    }

    #[Override]
    public function getPrice(): ?float
    {
        return 0;
    }

    #[Override]
    public function __toString(): string
    {
        return t('Donation', category: 'commerce');
    }

    #[Override]
    public static function displayName(): string
    {
        return t('Donation', category: 'commerce');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('donation', category: 'commerce');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Donations', category: 'commerce');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('donations', category: 'commerce');
    }

    #[Override]
    public static function refHandle(): ?string
    {
        return 'donation';
    }

    #[Override]
    public static function find(): DonationQuery
    {
        return new DonationQuery();
    }

    /**
     * Returns the product title and variants title together for variable products.
     */
    #[Override]
    public function getDescription(): string
    {
        return t('Donation', category: 'commerce');
    }

    #[Override]
    public function getCpEditUrl(): ?string
    {
        return Url::cpUrl(sprintf('commerce/store-management/%s/donation', $this->getStore()->handle));
    }

    #[Override]
    public function getUrl(): ?string
    {
        return '';
    }

    #[Override]
    public function hasFreeShipping(): bool
    {
        return true;
    }

    #[Override]
    public function getIsShippable(): bool
    {
        return false;
    }

    #[Override]
    public function getIsTaxable(): bool
    {
        return false;
    }

    #[Override]
    public function populateLineItem(LineItem $lineItem): void
    {
        $options = $lineItem->getOptions();
        if (isset($options['donationAmount'])) {
            $lineItem->price = $options['donationAmount'];
        }
    }

    #[Override]
    public function getLineItemRules(LineItem $lineItem): array
    {
        return [
            [
                'purchasableId',
                function($attribute, $params, Validator $validator) use ($lineItem) {
                    $options = $lineItem->getOptions();
                    if (!isset($options['donationAmount'])) {
                        $validator->addError($lineItem, $attribute, t('No donation amount supplied.', category: 'commerce'));
                    }
                    if (isset($options['donationAmount']) && !is_numeric($options['donationAmount'])) {
                        $validator->addError($lineItem, $attribute, t('Donation needs to be an amount.', category: 'commerce'));
                    }
                    if (isset($options['donationAmount']) && $options['donationAmount'] == 0) {
                        $validator->addError($lineItem, $attribute, t('Donation can not be zero.', category: 'commerce'));
                    }
                },
            ],
        ];
    }

    #[Override]
    public function getIsPromotable(): bool
    {
        return false;
    }

    #[Override]
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = DonationRecord::query()->findOrFail($this->id);
        } else {
            $record = new DonationRecord();
            $record->id = $this->id;
        }

        $record->sku = $this->sku;

        // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e resaving
        $record->dateUpdated = $this->dateUpdated;
        $record->dateCreated = $this->dateCreated;

        $record->save();

        parent::afterSave($isNew);

        // Loop through other stores to save the donation to all stores
        $stores = app(Stores::class)->getAllStores();
        $stores
            ->filter(fn(Store $s) => $s->id !== $this->getStore()->id)
            ->each(function(Store $store) use ($isNew) {
                $purchasableStoreRecord = PurchasableStoreRecord::findOne(['purchasableId' => $this->id, 'storeId' => $store->id]);
                if ($isNew || !$purchasableStoreRecord) {
                    $purchasableStoreRecord = new PurchasableStoreRecord();
                    $purchasableStoreRecord->purchasableId = $this->id;
                    $purchasableStoreRecord->storeId = $store->id;
                }

                $purchasableStoreRecord->basePrice = 0;
                $purchasableStoreRecord->basePromotionalPrice = null;
                $purchasableStoreRecord->stock = null;
                $purchasableStoreRecord->inventoryTracked = false;
                $purchasableStoreRecord->allowOutOfStockPurchases = false;
                $purchasableStoreRecord->minQty = null;
                $purchasableStoreRecord->maxQty = null;
                $purchasableStoreRecord->promotable = false;
                $purchasableStoreRecord->availableForPurchase = $this->availableForPurchase;
                $purchasableStoreRecord->freeShipping = true;
                $purchasableStoreRecord->shippingCategoryId = app(ShippingCategories::class)->getDefaultShippingCategory($store->id)->id;

                $purchasableStoreRecord->save(false);
            });
    }
}
