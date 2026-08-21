<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Models;

use CraftCms\Cms\Component\Component;

class PurchasableStore extends Component
{
    public ?int $id = null;

    public ?int $purchasableId = null;

    public ?int $storeId = null;

    public ?float $basePrice = null;

    public ?float $basePromotionalPrice = null;

    public ?int $stock = null;

    public bool $hasUnlimitedStock = false;

    public ?int $minQty = null;

    public ?int $maxQty = null;

    public bool $promotable = false;

    public bool $availableForPurchase = false;

    public bool $allowOutOfStockPurchases = false;

    public bool $freeShipping = false;

    public ?int $shippingCategoryId = null;

    #[\Override]
    public function getRules(): array
    {
        return [
            'purchasableId' => ['required', 'integer'],
            'storeId' => ['required', 'integer'],
            'stock' => ['nullable', 'integer'],
            'minQty' => ['nullable', 'integer'],
            'maxQty' => ['nullable', 'integer'],
            'basePrice' => ['nullable', 'numeric'],
            'basePromotionalPrice' => ['nullable', 'numeric'],
            'hasUnlimitedStock' => ['boolean'],
            'promotable' => ['boolean'],
            'availableForPurchase' => ['boolean'],
            'freeShipping' => ['boolean'],
            'allowOutOfStockPurchases' => ['boolean'],
        ];
    }
}
