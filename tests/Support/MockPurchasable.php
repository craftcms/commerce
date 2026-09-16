<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests\Support;

use CraftCms\Commerce\Purchasable\Elements\Purchasable;

/**
 * A minimal concrete `Purchasable` for tests that need to populate a `LineItem` from *some*
 * purchasable without setting up a real Product/Variant. Ports `tests-yii2/mockclasses/Purchasable.php`
 * — everything else (tax/shipping category, snapshot, etc.) falls through to the base class'
 * real implementation, which resolves against the store's defaults.
 */
class MockPurchasable extends Purchasable
{
    public bool $isPromotable = true;

    #[\Override]
    public function getIsPromotable(): bool
    {
        return $this->isPromotable;
    }

    #[\Override]
    public function getPrice(): ?float
    {
        return 25.10;
    }

    #[\Override]
    public function getSku(): string
    {
        return 'commerce_testing_unique_sku';
    }
}
