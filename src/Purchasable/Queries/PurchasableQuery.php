<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Queries;

use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\Commerce\Purchasable\Queries\Concerns\QueriesPurchasableAttributes;
use Illuminate\Support\Facades\DB;
use Tpetry\QueryExpressions\Language\Alias;
use function CraftCms\Cms\currentUser;

/**
 * @template TElement of Purchasable
 * @extends ElementQuery<TElement>
 */
abstract class PurchasableQuery extends ElementQuery
{
    use QueriesPurchasableAttributes;

    protected string $table = Table::PURCHASABLES;

    /** @var array<string, int> */
    protected array $defaultOrderBy = [
        'commerce_purchasables.sku' => SORT_ASC,
    ];

    public int|false|null $forCustomer = null;

    /**
     * Whether catalog pricing rules are active, decided once at construction time (the join/select
     * setup below depends on it), and reused by {@see QueriesPurchasableAttributes}'s price-family
     * `apply*()` methods so they always agree with which columns/joins are actually on the query.
     */
    public readonly bool $hasCatalogPricingRules;

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

        $this->hasCatalogPricingRules = app(CatalogPricingRules::class)->hasCatalogPricingRules();

        if ($this->hasCatalogPricingRules) {
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
        }
    }

    public function forCustomer(int|false|null $value = null): static
    {
        $this->forCustomer = $value;
        return $this;
    }
}
