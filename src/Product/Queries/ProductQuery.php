<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Queries;

use Closure;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\Support\Arr;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Concerns\QueriesProductTypeAuthorization;
use CraftCms\Commerce\Product\Queries\Concerns\QueriesProductDates;
use CraftCms\Commerce\Product\Queries\Concerns\QueriesProductDefaultVariant;
use CraftCms\Commerce\Product\Queries\Concerns\QueriesProductRef;
use CraftCms\Commerce\Product\Queries\Concerns\QueriesProductType;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\Queries\VariantQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Override;

use Tpetry\QueryExpressions\Language\Alias;
use function CraftCms\Cms\currentUser;

/**
 * @extends ElementQuery<Product>
 */
class ProductQuery extends ElementQuery
{
    use QueriesProductDates;
    use QueriesProductDefaultVariant;
    use QueriesProductRef;
    use QueriesProductType;
    use QueriesProductTypeAuthorization;

    #[Override]
    protected string $table = Table::PRODUCTS;

    /**
     * Products are structure-aware by default (product types may be structures), mirroring
     * {@see \CraftCms\Cms\Element\Queries\EntryQuery::$withStructure}.
     */
    #[Override]
    public bool $withStructure {
        get {
            if (!isset($this->withStructure)) {
                $this->withStructure = true;
            }

            return $this->withStructure;
        }
    }

    /** @var array<string, int> */
    #[Override]
    protected array $defaultOrderBy = [
        'commerce_products.postDate' => SORT_DESC,
        'elements.id' => SORT_DESC,
    ];

    /**
     * Only return products that match the resulting variant query.
     */
    public mixed $hasVariant = null;

    /**
     * Whether catalog pricing rules are active, decided once at construction time (the join/select
     * setup below depends on it), and reused by {@see QueriesProductDefaultVariant::applyDefaultPrice()}
     * so it always agrees with which columns/joins are actually on the query.
     */
    public readonly bool $hasCatalogPricingRules;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = [Product::STATUS_LIVE];
        }

        parent::__construct(Product::class, $config);

        $this->query->addSelect([
            'commerce_products.typeId',
            'commerce_products.postDate',
            'commerce_products.expiryDate',
            'commerce_products.defaultVariantId',
            'purchasables.sku as defaultSku',
            'purchasables.weight as defaultWeight',
            'purchasables.length as defaultLength',
            'purchasables.width as defaultWidth',
            'purchasables.height as defaultHeight',
            'purchasablesstores.basePrice as defaultBasePrice',
            'purchasablesstores.basePromotionalPrice as defaultBasePromotionalPrice',
            'sitestores.storeId',
        ]);

        // Join in site stores to get the product's store for the current request
        $this->query->leftJoin(new Alias(Table::SITESTORES, 'sitestores'), 'elements_sites.siteId', '=', 'sitestores.siteId');
        $this->query->leftJoin(new Alias(Table::PURCHASABLES, 'purchasables'), 'purchasables.id', '=', 'commerce_products.defaultVariantId');
        $this->query->leftJoin(new Alias(Table::PURCHASABLES_STORES, 'purchasablesstores'), function(JoinClause $join) {
            $join->on('purchasablesstores.purchasableId', '=', 'commerce_products.defaultVariantId')
                ->on('purchasablesstores.storeId', '=', 'sitestores.storeId');
        });

        // Tailor the query based on whether there are catalog pricing rules.
        // The legacy Yii2 query staged this through the element query's `subQuery`; the new
        // architecture is a single query, so the catalog prices are joined in directly here.
        $this->hasCatalogPricingRules = app(CatalogPricingRules::class)->hasCatalogPricingRules();

        if ($this->hasCatalogPricingRules) {
            $catalogPricesQuery = app(CatalogPricing::class)
                ->createCatalogPricesQuery(userId: currentUser()?->getCraftUserId())
                ->addSelect(['cp.purchasableId', 'cp.storeId']);

            $this->query->leftJoinSub($catalogPricesQuery, 'catalogprices', function(JoinClause $join) {
                $join->on('catalogprices.purchasableId', '=', 'commerce_products.defaultVariantId')
                    ->on('catalogprices.storeId', '=', 'sitestores.storeId');
            });

            $this->query->addSelect(['catalogprices.price as defaultPrice']);
        } else {
            $this->query->addSelect(['purchasablesstores.basePrice as defaultPrice']);
        }

        $this->beforeQuery(static function(self $productQuery) {
            static::applyHasVariant($productQuery, $productQuery->hasVariant);
        });
    }

    /**
     * Narrows the query results to only products that have certain variants.
     *
     * @param VariantQuery|array<string, mixed> $value
     */
    public function hasVariant(mixed $value): static
    {
        $this->hasVariant = $value;
        return $this;
    }

    /**
     * @param VariantQuery|array<string, mixed>|null $value
     *
     * @throws QueryAbortedException
     */
    public static function applyHasVariant(BuilderContract $query, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if ($value instanceof VariantQuery) {
            $variantQuery = $value;
        } elseif (is_array($value)) {
            $variantQuery = Variant::find();
            self::configure($variantQuery, $value);
        } else {
            throw new QueryAbortedException('Invalid param used. ProductQuery::hasVariant param only expects a variant query or variant query config.');
        }

        $variantQuery->limit(null);
        $variantQuery->whereNotNull('commerce_variants.primaryOwnerId');

        // The legacy query correlated a nested EXISTS subquery against `commerce_products.id`;
        // the resulting SQL is equivalent to (and simpler as) an IN subquery here.
        //
        // applyBeforeQueryCallbacks() must run before select(): it triggers VariantQuery's own
        // joinOwners() callback, which adds several of its own select columns (ownerId, sortOrder,
        // isDefault, etc). Selecting first would just have those columns appended on top of it,
        // producing an IN subquery with more than the single column it's allowed to have.
        $variantQuery->applyBeforeQueryCallbacks();
        $variantQuery->select('commerce_variants.primaryOwnerId as primaryOwnerId');
        $query->whereIn('commerce_products.id', $variantQuery->getQuery());
    }

    #[Override]
    protected function statusCondition(string $status): Closure
    {
        // Always consider “now” to be the current time @ 59 seconds into the minute, to keep
        // product queries cacheable (mirrors EntryQuery).
        $currentTime = now()->endOfMinute()->setTimezone('UTC');

        return match ($status) {
            Product::STATUS_LIVE => fn(Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->where('commerce_products.postDate', '<=', $currentTime)
                ->where(function(Builder $query) use ($currentTime) {
                    $query->whereNull('commerce_products.expiryDate')
                        ->orWhere('commerce_products.expiryDate', '>', $currentTime);
                }
            ),
            Product::STATUS_PENDING => fn(Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->where('commerce_products.postDate', '>', $currentTime),
            Product::STATUS_EXPIRED => fn(Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->whereNotNull('commerce_products.expiryDate')
                ->where('commerce_products.expiryDate', '<=', $currentTime),
            default => parent::statusCondition($status),
        };
    }

    #[Override]
    protected function cacheTags(): array
    {
        $tags = [];

        if ($this->typeId) {
            foreach (Arr::wrap($this->typeId) as $typeId) {
                $tags[] = "productType:$typeId";
            }
        }

        return $tags;
    }
}
