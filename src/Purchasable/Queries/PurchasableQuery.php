<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Queries;

use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\Commerce\Shipping\Data\ShippingCategory;
use CraftCms\Commerce\Tax\Data\TaxCategory;
use Illuminate\Support\Facades\DB;
use Tpetry\QueryExpressions\Language\Alias;
use function CraftCms\Cms\currentUser;

/**
 * @template TElement of Purchasable
 * @extends ElementQuery<TElement>
 */
abstract class PurchasableQuery extends ElementQuery
{
    protected string $table = Table::PURCHASABLES;

    /** @var array<string, int> */
    protected array $defaultOrderBy = [
        'commerce_purchasables.sku' => SORT_ASC,
    ];

    public ?bool $availableForPurchase = null;

    public mixed $sku = null;

    public mixed $price = null;

    public mixed $promotionalPrice = null;

    public ?bool $onPromotion = null;

    public mixed $salePrice = null;

    public mixed $width = false;

    public mixed $height = false;

    public mixed $length = false;

    public mixed $weight = false;

    public mixed $stock = null;

    public ?bool $hasStock = null;

    public mixed $shippingCategoryId = null;

    public mixed $taxCategoryId = null;

    public int|false|null $forCustomer = null;

    public ?bool $inventoryTracked = null;

    /** @param array<string, mixed> $config */
    public function __construct(string $elementType, array $config = [])
    {
        parent::__construct($elementType, $config);

        $this->query->addSelect([
            'commerce_purchasables.sku',
            'commerce_purchasables.width',
            'commerce_purchasables.height',
            'commerce_purchasables.length',
            'commerce_purchasables.weight',
            'commerce_purchasables.taxCategoryId',
            'purchasables_stores.availableForPurchase',
            'purchasables_stores.basePrice',
            'purchasables_stores.basePromotionalPrice',
            'purchasables_stores.freeShipping',
            'purchasables_stores.maxQty',
            'purchasables_stores.minQty',
            'purchasables_stores.inventoryTracked',
            'purchasables_stores.allowOutOfStockPurchases',
            'purchasables_stores.promotable',
            'purchasables_stores.shippingCategoryId',
            'inventoryitems.id as inventoryItemId',
        ]);

        $this->query->leftJoin(new Alias(Table::SITESTORES, 'sitestores'), 'elements_sites.siteId', '=', 'sitestores.siteId');
        $this->query->leftJoin(new Alias(Table::PURCHASABLES_STORES, 'purchasables_stores'), function($join) {
            $join->on('purchasables_stores.storeId', '=', 'sitestores.storeId')
                ->on('purchasables_stores.purchasableId', '=', 'commerce_purchasables.id');
        });
        $this->query->leftJoin(new Alias(Table::INVENTORYITEMS, 'inventoryitems'), 'inventoryitems.purchasableId', '=', 'commerce_purchasables.id');

        if (app(CatalogPricingRules::class)->hasCatalogPricingRules()) {
            $customerId = $this->forCustomer;
            if ($customerId === null) {
                $customerId = currentUser()?->getCraftUserId();
            } elseif ($customerId === false) {
                $customerId = null;
            }

            $catalogPricesQuery = app(CatalogPricing::class)
                ->createCatalogPricesQuery(userId: $customerId)
                ->addSelect(['cp.purchasableId', 'cp.storeId']);

            $this->query->leftJoinSub($catalogPricesQuery, 'catalogprices', function($join) {
                $join->on('catalogprices.purchasableId', '=', 'commerce_purchasables.id')
                    ->on('catalogprices.storeId', '=', 'sitestores.storeId');
            });

            // `salePrice` is deliberately not selected: it's a getter-only virtual attribute
            // (Purchasable::getSalePrice()) with no setter, so populating it from the row would
            // throw "Setting read-only property". It's still usable below as a where-filter column,
            // since that only references it, it doesn't try to write it back to the element.
            $this->query->addSelect([
                'catalogprices.price',
                'catalogprices.promotionalPrice',
            ]);

            // Joined here (rather than the previous correlated selectSub()), because
            // ElementQuery::applySelectParams() unwraps any Expression back into a plain
            // "column [as alias]" string and re-wraps it as an identifier, which mangles anything
            // more complex than a bare column reference (e.g. a subquery-as-column expression).
            $this->query->leftJoinSub(
                DB::table(Table::CATALOG_PRICING . ' as cpr')
                    ->select(['purchasableId', 'storeId', DB::raw('MIN(catalogPricingRuleId) as catalogPricingRuleId')])
                    ->whereNotNull('catalogPricingRuleId')
                    ->groupBy(['purchasableId', 'storeId']),
                'catalogpricingruleids',
                function($join) {
                    $join->on('catalogpricingruleids.purchasableId', '=', 'commerce_purchasables.id')
                        ->on('catalogpricingruleids.storeId', '=', 'sitestores.storeId');
                },
            );

            $this->query->addSelect(['catalogpricingruleids.catalogPricingRuleId']);

            if (isset($this->price)) {
                $this->query->whereParam('catalogprices.price', $this->price);
            }

            if (isset($this->promotionalPrice)) {
                $this->query->whereParam('catalogprices.promotionalPrice', $this->promotionalPrice);
            }

            if (isset($this->onPromotion)) {
                if ($this->onPromotion) {
                    $this->query->whereColumn('catalogprices.promotionalPrice', '<', 'catalogprices.price');
                } else {
                    $this->query->whereColumn('catalogprices.price', '=', 'catalogprices.promotionalPrice');
                }
            }

            if (isset($this->salePrice)) {
                $this->query->whereParam('catalogprices.salePrice', $this->salePrice);
            }
        } else {
            // `salePrice` and `catalogPricingRuleId` are deliberately not selected here: `salePrice` is a
            // getter-only virtual attribute (Purchasable::getSalePrice()) with no setter, so populating it
            // from the row would throw "Setting read-only property"; `catalogPricingRuleId` has no meaningful
            // value without catalog pricing rules and already defaults to null on the element. Both are still
            // usable below as where-filter expressions/columns, since that only reads them.
            $this->query->addSelect([
                'purchasables_stores.basePrice as price',
                'purchasables_stores.basePromotionalPrice as promotionalPrice',
            ]);

            if (isset($this->price)) {
                $this->query->whereParam('purchasables_stores.basePrice', $this->price);
            }

            if (isset($this->promotionalPrice)) {
                $this->query->whereParam('purchasables_stores.basePromotionalPrice', $this->promotionalPrice);
            }

            if (isset($this->onPromotion)) {
                if ($this->onPromotion) {
                    $this->query->whereColumn('purchasables_stores.basePromotionalPrice', '<', 'purchasables_stores.basePrice');
                } else {
                    $this->query->whereColumn('purchasables_stores.basePrice', '<', 'purchasables_stores.basePromotionalPrice');
                }
            }

            if (isset($this->salePrice)) {
                $this->query->whereParam(DB::raw('CASE WHEN purchasables_stores.basePromotionalPrice < purchasables_stores.basePrice THEN purchasables_stores.basePromotionalPrice ELSE purchasables_stores.basePrice END'), $this->salePrice);
            }
        }

        $this->beforeQuery(function(self $query) {
            if (isset($query->sku)) {
                $query->whereParam('commerce_purchasables.sku', $query->sku);
            }

            // We don't join the inventory levels table, and rely on the cached store available total.
            if (isset($query->stock)) {
                $query->whereParam('purchasables_stores.stock', $query->stock);
            }

            if (isset($query->inventoryTracked)) {
                $query->whereParam('purchasables_stores.inventoryTracked', $query->inventoryTracked);
            }

            if (isset($query->availableForPurchase)) {
                $query->where('purchasables_stores.availableForPurchase', $query->availableForPurchase);
            }

            if (isset($query->shippingCategoryId)) {
                $query->whereParam('purchasables_stores.shippingCategoryId', $query->shippingCategoryId);
            }

            if (isset($query->taxCategoryId)) {
                $query->whereParam('commerce_purchasables.taxCategoryId', $query->taxCategoryId);
            }

            if ($query->width !== false) {
                if ($query->width === null) {
                    $query->whereNull('commerce_purchasables.width');
                } else {
                    $query->whereParam('commerce_purchasables.width', $query->width);
                }
            }

            if ($query->height !== false) {
                if ($query->height === null) {
                    $query->whereNull('commerce_purchasables.height');
                } else {
                    $query->whereParam('commerce_purchasables.height', $query->height);
                }
            }

            if ($query->length !== false) {
                if ($query->length === null) {
                    $query->whereNull('commerce_purchasables.length');
                } else {
                    $query->whereParam('commerce_purchasables.length', $query->length);
                }
            }

            if ($query->weight !== false) {
                if ($query->weight === null) {
                    $query->whereNull('commerce_purchasables.weight');
                } else {
                    $query->whereParam('commerce_purchasables.weight', $query->weight);
                }
            }

            if (isset($query->hasStock)) {
                if ($query->hasStock) {
                    $query->where(function($q) {
                        $q->where('purchasables_stores.inventoryTracked', false)
                            ->orWhere(function($q2) {
                                $q2->where('purchasables_stores.inventoryTracked', true)
                                    ->where('purchasables_stores.stock', '>', 0);
                            });
                    });
                } else {
                    $query->where('purchasables_stores.inventoryTracked', true)
                        ->where('purchasables_stores.stock', '<', 1);
                }
            }
        });
    }

    public function availableForPurchase(?bool $value = true): static
    {
        $this->availableForPurchase = $value;
        return $this;
    }

    public function sku(mixed $value): static
    {
        $this->sku = $value;
        return $this;
    }

    public function stock(mixed $value): static
    {
        $this->stock = $value;
        return $this;
    }

    public function hasStock(?bool $value = true): static
    {
        $this->hasStock = $value;
        return $this;
    }

    public function forCustomer(int|false|null $value = null): static
    {
        $this->forCustomer = $value;
        return $this;
    }

    public function width(mixed $value): static
    {
        $this->width = $value;
        return $this;
    }

    public function height(mixed $value): static
    {
        $this->height = $value;
        return $this;
    }

    public function length(mixed $value): static
    {
        $this->length = $value;
        return $this;
    }

    public function weight(mixed $value): static
    {
        $this->weight = $value;
        return $this;
    }

    public function price(mixed $value): static
    {
        $this->price = $value;
        return $this;
    }

    public function inventoryTracked(?bool $value = true): static
    {
        $this->inventoryTracked = $value;
        return $this;
    }

    public function promotionalPrice(mixed $value): static
    {
        $this->promotionalPrice = $value;
        return $this;
    }

    public function salePrice(mixed $value): static
    {
        $this->salePrice = $value;
        return $this;
    }

    public function shippingCategoryId(mixed $value): static
    {
        $this->shippingCategoryId = $value;
        return $this;
    }

    public function shippingCategory(mixed $value): static
    {
        if ($value instanceof ShippingCategory) {
            $this->shippingCategoryId = [$value->id];
        } elseif ($value !== null) {
            $this->shippingCategoryId = DB::table(Table::SHIPPINGCATEGORIES . ' as shippingcategories')
                ->whereColumn('shippingcategories.id', 'purchasables_stores.shippingCategoryId')
                ->whereParam('handle', $value)
                ->select('shippingcategories.id');
        } else {
            $this->shippingCategoryId = null;
        }

        return $this;
    }

    public function taxCategoryId(mixed $value): static
    {
        $this->taxCategoryId = $value;
        return $this;
    }

    public function taxCategory(mixed $value): static
    {
        if ($value instanceof TaxCategory) {
            $this->taxCategoryId = [$value->id];
        } elseif ($value !== null) {
            $this->taxCategoryId = DB::table(Table::TAXCATEGORIES . ' as taxcategories')
                ->whereColumn('taxcategories.id', 'commerce_purchasables.taxCategoryId')
                ->whereParam('handle', $value)
                ->select('taxcategories.id');
        } else {
            $this->taxCategoryId = null;
        }

        return $this;
    }

    public function onPromotion(?bool $value = true): static
    {
        $this->onPromotion = $value;
        return $this;
    }
}
