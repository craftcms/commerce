<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Queries;

use Closure;
use craft\commerce\Plugin;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\Support\Arr;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Database\Table;
use DateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Override;
use Tpetry\QueryExpressions\Language\Alias;

use function CraftCms\Cms\currentUser;

/**
 * @extends ElementQuery<Product>
 */
class ProductQuery extends ElementQuery
{
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
     * Whether to only return products that the user has permission to view.
     */
    public ?bool $editable = null;

    /**
     * Whether to only return products that the user has permission to save.
     */
    public ?bool $savable = null;

    public mixed $expiryDate = null;

    public mixed $defaultPrice = null;

    public mixed $defaultHeight = null;

    public mixed $defaultLength = null;

    public mixed $defaultWidth = null;

    public mixed $defaultWeight = null;

    public mixed $defaultSku = null;

    /**
     * Only return products that match the resulting variant query.
     */
    public mixed $hasVariant = null;

    public mixed $postDate = null;

    public mixed $typeId = null;

    /**
     * The reference code(s) used to identify the product(s), e.g. `{product:productTypeHandle/slug}`.
     */
    public mixed $ref = null;

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
        $hasCatalogPricingRules = app(CatalogPricingRules::class)->hasCatalogPricingRules();

        if ($hasCatalogPricingRules) {
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

        $this->beforeQuery(function(self $query) use ($hasCatalogPricingRules) {
            $query->normalizeTypeId();

            // See if 'type' was set to an invalid handle
            if ($query->typeId === []) {
                throw new QueryAbortedException();
            }

            if (isset($query->defaultPrice)) {
                $query->whereParam(
                    $hasCatalogPricingRules ? 'catalogprices.price' : 'purchasablesstores.basePrice',
                    $query->defaultPrice,
                );
            }

            if (isset($query->postDate)) {
                $query->whereDateParam('commerce_products.postDate', $query->postDate);
            }

            if (isset($query->expiryDate)) {
                $query->whereDateParam('commerce_products.expiryDate', $query->expiryDate);
            }

            if (isset($query->defaultHeight)) {
                $query->whereParam('purchasables.height', $query->defaultHeight);
            }

            if (isset($query->defaultLength)) {
                $query->whereParam('purchasables.length', $query->defaultLength);
            }

            if (isset($query->defaultWidth)) {
                $query->whereParam('purchasables.width', $query->defaultWidth);
            }

            if (isset($query->defaultWeight)) {
                $query->whereParam('purchasables.weight', $query->defaultWeight);
            }

            if (isset($query->defaultSku)) {
                $query->whereParam('purchasables.sku', $query->defaultSku);
            }

            $query->applyProductTypeIdParam();
            $query->applyHasVariantParam();
            // Mirrors EntryQuery: "editable" means accessible in the editing UI (view permission),
            // not necessarily savable. Use ->savable() to filter by save permission.
            $query->applyPermissionParam($query->editable, 'commerce-viewProductType');
            $query->applyPermissionParam($query->savable, 'commerce-saveProductType');
            $query->applyRefParam();
        });
    }

    /**
     * Narrows the query results based on the products’ default variant price.
     */
    public function defaultPrice(mixed $value): static
    {
        $this->defaultPrice = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant height.
     */
    public function defaultHeight(mixed $value): static
    {
        $this->defaultHeight = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant length.
     */
    public function defaultLength(mixed $value): static
    {
        $this->defaultLength = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant width.
     */
    public function defaultWidth(mixed $value): static
    {
        $this->defaultWidth = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant weight.
     */
    public function defaultWeight(mixed $value): static
    {
        $this->defaultWeight = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant SKU.
     */
    public function defaultSku(mixed $value): static
    {
        $this->defaultSku = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ types.
     *
     * @param ProductType|string|string[]|null $value
     */
    public function type(mixed $value): static
    {
        // TODO: migrate to app(ProductTypes::class)->getProductTypeByHandle() once service migrated to src/
        /** @phpstan-ignore-next-line */
        if (is_string($value) && ($productType = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle($value))) {
            $value = $productType;
        }

        if ($value instanceof ProductType) {
            $this->typeId = [$value->id];
        } elseif ($value !== null) {
            $this->typeId = DB::table(Table::PRODUCTTYPES)
                ->whereParam('handle', $value)
                ->pluck('id')
                ->all();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results to only products that were posted before a certain date.
     */
    public function before(DateTime|string $value): static
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = Arr::wrap($this->postDate);
        $this->postDate[] = '<' . $value;

        return $this;
    }

    /**
     * Narrows the query results to only products that were posted on or after a certain date.
     */
    public function after(DateTime|string $value): static
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = Arr::wrap($this->postDate);
        $this->postDate[] = '>=' . $value;

        return $this;
    }

    public function editable(?bool $value = true): static
    {
        $this->editable = $value;
        return $this;
    }

    public function savable(?bool $value = true): static
    {
        $this->savable = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ types, per the types’ IDs.
     */
    public function typeId(mixed $value): static
    {
        $this->typeId = $value;
        return $this;
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
     * Narrows the query results based on the products’ post dates.
     */
    public function postDate(mixed $value): static
    {
        $this->postDate = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ expiry dates.
     */
    public function expiryDate(mixed $value): static
    {
        $this->expiryDate = $value;
        return $this;
    }

    /**
     * Narrows the query results based on a reference string.
     */
    public function ref(mixed $value): static
    {
        $this->ref = $value;
        return $this;
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

    /**
     * Normalizes the typeId param to an array of IDs or null.
     */
    private function normalizeTypeId(): void
    {
        if (empty($this->typeId)) {
            $this->typeId = is_array($this->typeId) ? [] : null;
        } elseif (is_numeric($this->typeId)) {
            $this->typeId = [$this->typeId];
        } elseif (!is_array($this->typeId) || !Arr::isNumeric($this->typeId)) {
            $this->typeId = DB::table(Table::PRODUCTTYPES)
                ->whereParam('id', $this->typeId)
                ->pluck('id')
                ->all();
        }
    }

    /**
     * Applies the 'typeId' param to the query being prepared.
     */
    private function applyProductTypeIdParam(): void
    {
        if (!$this->typeId) {
            return;
        }

        $this->whereIn('commerce_products.typeId', $this->typeId);

        // Should we set the structureId param?
        if (
            $this->withStructure !== false &&
            !isset($this->structureId) &&
            count($this->typeId) === 1
        ) {
            // TODO: migrate to app(ProductTypes::class)->getProductTypeById() once service migrated to src/
            /** @phpstan-ignore-next-line */
            $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById((int)reset($this->typeId));

            if ($productType && $productType->isStructure) {
                $this->structureId = $productType->structureId;
            } else {
                $this->withStructure = false;
            }
        }
    }

    /**
     * Applies the 'hasVariant' param to the query being prepared.
     *
     * @throws QueryAbortedException
     */
    private function applyHasVariantParam(): void
    {
        if ($this->hasVariant === null) {
            return;
        }

        if ($this->hasVariant instanceof VariantQuery) {
            $variantQuery = $this->hasVariant;
        } elseif (is_array($this->hasVariant)) {
            $variantQuery = Variant::find();
            self::configure($variantQuery, $this->hasVariant);
        } else {
            throw new QueryAbortedException('Invalid param used. ProductQuery::hasVariant param only expects a variant query or variant query config.');
        }

        $variantQuery->limit(null);
        $variantQuery->select('commerce_variants.primaryOwnerId as primaryOwnerId');
        $variantQuery->whereNotNull('commerce_variants.primaryOwnerId');

        // The legacy query correlated a nested EXISTS subquery against `commerce_products.id`;
        // the resulting SQL is equivalent to (and simpler as) an IN subquery here.
        $variantQuery->applyBeforeQueryCallbacks();
        $this->whereIn('commerce_products.id', $variantQuery->getQuery());
    }

    /**
     * Applies an authorization param to the query being prepared.
     *
     * @throws QueryAbortedException
     */
    private function applyPermissionParam(?bool $value, string $permissionPrefix): void
    {
        if ($value === null) {
            return;
        }

        $user = currentUser();

        if (!$user) {
            throw new QueryAbortedException();
        }

        // TODO: migrate to app(ProductTypes::class)->getAllProductTypes() once service migrated to src/
        /** @phpstan-ignore-next-line */
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();

        if (empty($productTypes)) {
            return;
        }

        $authorizedTypeIds = [];

        foreach ($productTypes as $productType) {
            if ($user->can("$permissionPrefix:$productType->uid")) {
                $authorizedTypeIds[] = $productType->id;
            }
        }

        if (count($authorizedTypeIds) === count($productTypes)) {
            // They have access to everything
            if (!$value) {
                throw new QueryAbortedException();
            }
            return;
        }

        if (empty($authorizedTypeIds)) {
            // They don't have access to anything
            if ($value) {
                throw new QueryAbortedException();
            }
            return;
        }

        if ($value) {
            $this->whereIn('commerce_products.typeId', $authorizedTypeIds);
        } else {
            $this->whereNotIn('commerce_products.typeId', $authorizedTypeIds);
        }
    }

    /**
     * Applies the 'ref' param to the query being prepared.
     */
    private function applyRefParam(): void
    {
        if (!$this->ref) {
            return;
        }

        $refs = Arr::wrap($this->ref);
        $joinProductTypes = false;

        $this->where(function(Builder $query) use (&$joinProductTypes, $refs) {
            foreach ($refs as $ref) {
                $parts = array_filter(explode('/', (string)$ref), static fn(string $part) => $part !== '');

                if (empty($parts)) {
                    continue;
                }

                if (count($parts) === 1) {
                    $query->orWhereParam('elements_sites.slug', reset($parts));
                    continue;
                }

                $parts = array_values($parts);

                $query->orWhere(function(Builder $query) use ($parts) {
                    $query->whereParam('commerce_producttypes.handle', $parts[0])
                        ->whereParam('elements_sites.slug', $parts[1]);
                });

                $joinProductTypes = true;
            }
        });

        if ($joinProductTypes) {
            $this->join(new Alias(Table::PRODUCTTYPES, 'commerce_producttypes'), 'commerce_producttypes.id', '=', 'commerce_products.typeId');
        }
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
