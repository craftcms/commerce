<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Queries;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Queries\Concerns\QueriesNestedElements;
use CraftCms\Cms\Element\Queries\Contracts\NestedElementQueryInterface;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Arr;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\ProductQuery as ProductQueryHelper;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\ProductType\Concerns\QueriesProductTypeAuthorization;
use CraftCms\Commerce\Product\Queries\ProductQuery;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\Elements\VariantCollection;
use CraftCms\Commerce\Product\Variant\Queries\Concerns\QueriesVariantProductStatus;
use CraftCms\Commerce\Product\Variant\Queries\Concerns\QueriesVariantProductType;
use CraftCms\Commerce\Product\Variant\Queries\Concerns\QueriesVariantSales;
use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;

use Tpetry\QueryExpressions\Language\Alias;

/**
 * @extends PurchasableQuery<Variant>
 */
class VariantQuery extends PurchasableQuery implements NestedElementQueryInterface
{
    /**
     * The `QueriesNestedElements` concern is used for its param API (`owner()`, `ownerId()`,
     * `primaryOwner()`, `primaryOwnerId()`, `field()`, `fieldId()`, `allowOwnerDrafts()`,
     * `allowOwnerRevisions()`) and its cache tags. Its own `initQueriesNestedElements()` and
     * `fieldLayouts()` are replaced below: those two methods route through private helpers whose
     * signatures are hard-typed to core's `AddressQuery|ContentBlockQuery|EntryQuery` union, so
     * calling them with a commerce query would throw a `TypeError`. Variants also need a bespoke
     * `elements_owners` join anyway (they're not stored in a field, so there's no `fieldId`).
     */
    use QueriesNestedElements {
        cacheTags as nestedTraitCacheTags;
    }
    use QueriesProductTypeAuthorization;
    use QueriesVariantProductStatus;
    use QueriesVariantProductType;
    use QueriesVariantSales;

    /** @var array<string, int> */
    #[Override]
    protected array $defaultOrderBy = ['elements_owners.sortOrder' => SORT_ASC];

    /**
     * Only return variants that match the resulting product query.
     */
    public mixed $hasProduct = null;

    public ?bool $isDefault = null;

    public mixed $minQty = null;

    public mixed $maxQty = null;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = [Element::STATUS_ENABLED];
        }

        parent::__construct(Variant::class, $config);

        $this->query->join(new Alias(Table::VARIANTS, 'commerce_variants'), 'commerce_variants.id', '=', 'elements.id');

        $this->query->addSelect([
            'commerce_variants.primaryOwnerId',
        ]);

        $this->beforeQuery(static function(self $variantQuery) {
            // joinOwners() must run before anything below that references commerce_products.*/
            // commerce_producttypes.* columns — see the NOTE on QueriesVariantProductType,
            // QueriesVariantProductStatus, QueriesVariantSales, and QueriesProductTypeAuthorization
            // for why those traits don't self-register their own beforeQuery callback.
            $variantQuery->joinOwners();

            if ($variantQuery->primaryOwnerId) {
                $variantQuery->whereIn('commerce_variants.primaryOwnerId', $variantQuery->primaryOwnerId);
            }

            static::applyTypeId($variantQuery, $variantQuery->typeId);
            static::applyIsDefault($variantQuery, $variantQuery->isDefault);
            static::applyMinQty($variantQuery, $variantQuery->minQty);
            static::applyMaxQty($variantQuery, $variantQuery->maxQty);
            static::applyRequiresDimensionSupport($variantQuery, $variantQuery->width, $variantQuery->height, $variantQuery->length, $variantQuery->weight);
            static::applyProductStatus($variantQuery, $variantQuery->productStatus);
            static::applyHasSales($variantQuery, $variantQuery->hasSales, $variantQuery->siteId);
            static::applyHasProduct($variantQuery, $variantQuery->hasProduct);
            static::applyEditable($variantQuery, $variantQuery->editable);
            static::applySavable($variantQuery, $variantQuery->savable);
        });
    }

    public static function getFieldIdColumn(): string
    {
        // Variants aren't stored in a custom field, so there is no `fieldId` column. The primary
        // owner column is returned here for parity with the legacy query.
        return 'commerce_variants.primaryOwnerId';
    }

    public static function getPrimaryOwnerIdColumn(): string
    {
        return 'commerce_variants.primaryOwnerId';
    }

    /**
     * Replaces {@see QueriesNestedElements::initQueriesNestedElements()} — see the trait import
     * note on this class. The `elements_owners` join (and the product/product type joins that
     * hang off it) are set up by {@see joinOwners()} instead.
     */
    protected function initQueriesNestedElements(): void
    {
    }

    /**
     * Disables {@see QueriesProductTypeAuthorization}'s self-registered beforeQuery callback — see
     * the NOTE on {@see QueriesVariantProductType} for why. `applyEditable()`/`applySavable()` are
     * called directly from this class's own constructor instead, after `joinOwners()`.
     */
    protected function initQueriesProductTypeAuthorization(): void
    {
    }

    /** @return Collection<int, FieldLayout> */
    #[Override]
    protected function fieldLayouts(): Collection
    {
        // Bypasses QueriesNestedElements::fieldLayouts(), which normalizes the `fieldId` param via
        // a core-only-typed helper. Variants get their field layouts from their product types,
        // which are registered against this element type.
        return parent::fieldLayouts();
    }

    /**
     * Narrows the query results based on the variants’ product.
     */
    public function product(mixed $value): static
    {
        if ($value instanceof ElementInterface) {
            $this->ownerId = [$value->id];
        } else {
            $this->ownerId = $value;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the variants’ owner.
     *
     * Widened from {@see QueriesNestedElements::owner()} to also accept owner IDs, matching the
     * legacy query's behavior.
     */
    public function owner(mixed $value): static
    {
        /** @phpstan-ignore-next-line instanceof.alwaysTrue (widened to also accept owner IDs - PHPStan appears to be using NestedElementQueryInterface::owner()'s stricter ElementInterface param type here, not this override's mixed) */
        if ($value instanceof ElementInterface) {
            $this->ownerId = [$value->id];
        } else {
            $this->ownerId = $value;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the variants’ primary owner.
     *
     * Widened from {@see QueriesNestedElements::primaryOwner()} to also accept owner IDs, matching
     * the legacy query's behavior.
     */
    public function primaryOwner(mixed $value): static
    {
        /** @phpstan-ignore-next-line instanceof.alwaysTrue (widened to also accept owner IDs - PHPStan appears to be using NestedElementQueryInterface::primaryOwner()'s stricter ElementInterface param type here, not this override's mixed) */
        if ($value instanceof ElementInterface) {
            $this->primaryOwnerId = [$value->id];
        } else {
            $this->primaryOwnerId = $value;
        }

        return $this;
    }

    /**
     * Narrows the query results based on the variants’ products’ IDs.
     */
    public function productId(mixed $value): static
    {
        $this->ownerId = $value;
        return $this;
    }

    /**
     * Narrows the query results to only default variants.
     */
    public function isDefault(?bool $value = true): static
    {
        $this->isDefault = $value;
        return $this;
    }

    /**
     * Narrows the query results to only variants for certain products.
     *
     * @param ProductQuery|array<string, mixed> $value
     */
    public function hasProduct(mixed $value = []): static
    {
        $this->hasProduct = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ min quantity.
     */
    public function minQty(mixed $value): static
    {
        $this->minQty = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the variants’ max quantity.
     */
    public function maxQty(mixed $value): static
    {
        $this->maxQty = $value;
        return $this;
    }

    public static function applyIsDefault(BuilderContract $query, ?bool $value): void
    {
        if (!isset($value)) {
            return;
        }

        if ($value) {
            $query->whereColumn('commerce_variants.id', '=', 'commerce_products.defaultVariantId');
        } else {
            $query->where(function(Builder $subQuery) {
                $subQuery->whereNull('commerce_products.defaultVariantId')
                    ->orWhereColumn('commerce_variants.id', '!=', 'commerce_products.defaultVariantId');
            });
        }
    }

    /**
     * `minQty`/`maxQty` live on the purchasable's per-store row, not on `commerce_variants` (the
     * legacy query filtered `commerce_variants.minQty`/`maxQty`, which no longer exist as columns
     * — those params raised a SQL error).
     */
    public static function applyMinQty(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('purchasables_stores.minQty', $value);
    }

    public static function applyMaxQty(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('purchasables_stores.maxQty', $value);
    }

    /**
     * If width, height or length is specified in the query we should only be looking for products
     * that have a type which supports dimensions.
     */
    public static function applyRequiresDimensionSupport(BuilderContract $query, mixed $width, mixed $height, mixed $length, mixed $weight): void
    {
        if ($width !== false || $height !== false || $length !== false || $weight !== false) {
            $query->whereParam('commerce_producttypes.hasDimensions', true);
        }
    }

    /**
     * @param ProductQuery|array<string, mixed>|null $value
     */
    public static function applyHasProduct(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        if ($value instanceof ProductQuery) {
            $productQuery = $value;
        } else {
            $productQuery = Product::find();
            self::configure($productQuery, ProductQueryHelper::cleanseQueryCriteria($value));
        }

        $productQuery->limit(null);
        $productQuery->select('commerce_products.id as id');
        $productQuery->whereNotNull('commerce_products.id');
        $productQuery->applyBeforeQueryCallbacks();

        $query->whereIn('commerce_variants.primaryOwnerId', $productQuery->getQuery());
    }

    #[Override]
    public function collect(): VariantCollection
    {
        return VariantCollection::make(parent::collect()->all());
    }

    /**
     * Joins the `elements_owners` table, plus the owner product, its product type, and the owner
     * product's site settings — all of which the variant's selected columns and params rely on.
     */
    private function joinOwners(): void
    {
        $this->primaryOwnerId = $this->normalizeOwnerIdParam($this->primaryOwnerId, 'primaryOwnerId');
        $this->ownerId = $this->normalizeOwnerIdParam($this->ownerId, 'ownerId');

        $ownerId = $this->ownerId;

        $this->query
            ->addSelect([
                'elements_owners.ownerId as ownerId',
                'elements_owners.sortOrder as sortOrder',
            ])
            ->join(new Alias(CraftTable::ELEMENTS_OWNERS, 'elements_owners'), function(JoinClause $join) use ($ownerId) {
                $join->on('elements_owners.elementId', '=', 'elements.id');

                if ($ownerId) {
                    $join->whereIn('elements_owners.ownerId', $ownerId);
                } else {
                    $join->whereColumn('elements_owners.ownerId', 'commerce_variants.primaryOwnerId');
                }
            });

        $this->query->leftJoin(new Alias(Table::PRODUCTS, 'commerce_products'), 'commerce_products.id', '=', 'elements_owners.ownerId');
        $this->query->leftJoin(new Alias(Table::PRODUCTTYPES, 'commerce_producttypes'), 'commerce_producttypes.id', '=', 'commerce_products.typeId');
        $this->query->leftJoin(new Alias(CraftTable::ELEMENTS_SITES, 'commerce_products_elements_sites'), function(JoinClause $join) {
            $join->on('commerce_products_elements_sites.elementId', '=', 'elements_owners.ownerId')
                ->on('commerce_products_elements_sites.siteId', '=', 'elements_sites.siteId');
        });

        $this->query->addSelect([
            'commerce_products_elements_sites.slug as productSlug',
            'commerce_producttypes.handle as productTypeHandle',
        ]);

        // Whether this variant is its owner product's default variant.
        //
        // The legacy query derived this with a `CASE WHEN … END` select expression; the new element
        // query re-wraps any select expression as an identifier (see the note in PurchasableQuery),
        // so the same comparison is expressed as a joined subquery whose column is either the
        // variant's ID (truthy → default) or `null`.
        $this->query->leftJoinSub(
            DB::table(Table::PRODUCTS)
                ->select(['id as defaultForProductId', 'defaultVariantId'])
                ->whereNotNull('defaultVariantId'),
            'commerce_default_variants',
            function(JoinClause $join) {
                $join->on('commerce_default_variants.defaultVariantId', '=', 'commerce_variants.id')
                    ->on('commerce_default_variants.defaultForProductId', '=', 'commerce_products.id');
            },
        );

        $this->query->addSelect(['commerce_default_variants.defaultVariantId as isDefault']);
    }

    /**
     * Normalizes an owner ID param to an array of IDs or null.
     *
     * @return int[]|null
     *
     * @throws QueryAbortedException if the param value isn't a valid ID or set of IDs
     */
    private function normalizeOwnerIdParam(mixed $value, string $param): ?array
    {
        $normalized = $this->normalizeOwnerId($value);

        if ($normalized === false) {
            throw new QueryAbortedException("Invalid $param param value");
        }

        return $normalized;
    }

    #[Override]
    protected function cacheTags(): array
    {
        $tags = [];

        if ($this->ownerId) {
            foreach (Arr::wrap($this->ownerId) as $ownerId) {
                $tags[] = "product:$ownerId";
            }
        }

        array_push($tags, ...$this->nestedTraitCacheTags());

        return $tags;
    }
}
