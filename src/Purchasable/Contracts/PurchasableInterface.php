<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Contracts;

use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\Store;
use craft\commerce\models\TaxCategory;

interface PurchasableInterface extends ElementInterface
{
    public function getStore(): Store;

    public function getStoreId(): int;

    public function getPrice(): ?float;

    public function getPromotionalPrice(): ?float;

    public function getSalePrice(): ?float;

    public function getSku(): string;

    public function getDescription(): string;

    public function getTaxCategory(): TaxCategory;

    public function getShippingCategory(): ShippingCategory;

    public function getIsAvailable(): bool;

    public function populateLineItem(LineItem $lineItem): void;

    public function getSnapshot(): array;

    public function getLineItemRules(LineItem $lineItem): array;

    public function afterOrderComplete(Order $order, LineItem $lineItem): void;

    public function hasFreeShipping(): bool;

    public function getIsShippable(): bool;

    public function getIsTaxable(): bool;

    public function getIsPromotable(): bool;

    public function getPromotionRelationSource(): mixed;
}
