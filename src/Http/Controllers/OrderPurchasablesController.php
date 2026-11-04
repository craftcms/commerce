<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Support\Money;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\Purchasable as PurchasableHelper;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\Commerce\Purchasable\Events\ModifyPurchasablesTableQueryEvent;
use CraftCms\Commerce\Purchasable\Purchasables;
use CraftCms\Commerce\Purchasable\PurchasableTypes;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * Lists the purchasables an order can be given, for the order edit screen's "Add a line item" dialog.
 *
 * Every registered purchasable type is listed together, found through the shared purchasables table.
 *
 * @since 6.0.0
 */
class OrderPurchasablesController
{
    private const int DEFAULT_PER_PAGE = 25;

    private const int MAX_PER_PAGE = 100;

    private const array SORTS = ['description', 'sku', 'type', 'price'];

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'orderId' => ['required', 'integer'],
            'search' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1'],
        ]);

        $order = $this->order((int)$data['orderId']);
        $types = app(PurchasableTypes::class)->types()->values()->all();
        $search = isset($data['search']) && trim($data['search']) !== '' ? trim($data['search']) : null;
        $type = in_array($data['type'] ?? null, $types, true) ? $data['type'] : null;
        $page = (int)($data['page'] ?? 1);
        $perPage = min((int)($data['perPage'] ?? self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        $sort = in_array($data['sort'] ?? null, self::SORTS, true) ? $data['sort'] : 'description';
        $direction = ($data['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $liveCatalogPrices = app(CatalogPricingRules::class)->hasCatalogPricingRules();

        $query = $this->query($order, $types, $liveCatalogPrices);

        if ($type) {
            $query->where('elements.type', $type);
        }

        if ($search) {
            $this->applySearch($query, $search);
        }

        $this->applySort($query, $sort, $direction, $liveCatalogPrices);

        event($event = new ModifyPurchasablesTableQueryEvent($query, $search, $order));
        $query = $event->query;

        $total = (clone $query)->reorder()->count();
        $rows = $query->forPage($page, $perPage)->get();

        return new JsonResponse([
            'data' => $this->rows($rows->all(), $order),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'types' => array_map(fn(string $class) => ['type' => $class, 'label' => $class::displayName()], $types),
            'priceSortedBy' => $liveCatalogPrices ? 'live' : 'base',
        ]);
    }

    private function order(int $orderId): Order
    {
        $user = currentUserElement() ?? abort(403);
        $order = app(Orders::class)->getOrderById($orderId);

        abort_if(!$order, 404, t('Can not find order.', category: 'commerce'));
        abort_unless($order->canView($user) && $order->canSave($user), 403, 'User not authorized to edit this order.');

        return $order;
    }

    /**
     * @param list<class-string<PurchasableInterface>> $types
     */
    private function query(Order $order, array $types, bool $liveCatalogPrices): Builder
    {
        $query = DB::table(Table::PURCHASABLES . ' as purchasables');
        $query
            ->select([
                'purchasables.id',
                'purchasables.description',
                'purchasables.sku',
                'elements.type',
            ])
            ->join(CraftTable::ELEMENTS . ' as elements', 'elements.id', '=', 'purchasables.id')
            ->join(CraftTable::ELEMENTS_SITES . ' as es', function($join) use ($order) {
                $join->on('es.elementId', '=', 'purchasables.id')->where('es.siteId', $order->orderSiteId);
            })
            ->join(Table::PURCHASABLES_STORES . ' as pstores', function($join) use ($order) {
                $join->on('pstores.purchasableId', '=', 'purchasables.id')->where('pstores.storeId', $order->storeId);
            })
            ->whereIn('elements.type', $types)
            ->where('elements.enabled', true)
            ->whereNull('elements.revisionId')
            ->whereNull('elements.draftId')
            ->whereNull('elements.dateDeleted')
            ->whereRaw('SUBSTR(' . $query->getGrammar()->wrap('purchasables.sku') . ', 1, ?) != ?', [strlen(PurchasableHelper::TEMPORARY_SKU_PREFIX), PurchasableHelper::TEMPORARY_SKU_PREFIX]);

        if ($liveCatalogPrices) {
            $prices = app(CatalogPricing::class)
                ->createCatalogPricesQuery(userId: $order->getPricingCustomerId() ?: null, storeId: $order->storeId)
                ->addSelect(['cp.purchasableId', 'cp.storeId']);

            $query->leftJoinSub($prices, 'catalogprices', function($join) {
                $join->on('catalogprices.purchasableId', '=', 'purchasables.id')
                    ->on('catalogprices.storeId', '=', 'pstores.storeId');
            });
        }

        return $query;
    }

    private function applySearch(Builder $query, string $search): void
    {
        $query->where(function(Builder $query) use ($search) {
            $query->whereLike('purchasables.description', '%' . str_replace(' ', '%', $search) . '%')
                ->orWhereLike('purchasables.sku', '%' . $search . '%');
        });
    }

    private function applySort(Builder $query, string $sort, string $direction, bool $liveCatalogPrices): void
    {
        if ($sort === 'price') {
            $query->orderByRaw($this->priceSql($query, $liveCatalogPrices) . ' ' . $direction);
        } elseif ($sort === 'type') {
            $query->orderBy('elements.type', $direction);
        } else {
            $query->orderBy('purchasables.' . $sort, $direction);
        }

        if ($sort !== 'description') {
            $query->orderBy('purchasables.description');
        }

        $query->orderBy('purchasables.id');
    }

    /**
     * The price the purchasable is sold at: the promotional price when there is a lower one.
     */
    private function priceSql(Builder $query, bool $liveCatalogPrices): string
    {
        $grammar = $query->getGrammar();

        if (!$liveCatalogPrices) {
            return $grammar->wrap('pstores.basePrice');
        }

        $price = 'COALESCE(' . $grammar->wrap('catalogprices.price') . ', ' . $grammar->wrap('pstores.basePrice') . ')';
        $promotionalPrice = 'COALESCE(' . $grammar->wrap('catalogprices.promotionalPrice') . ', ' . $grammar->wrap('pstores.basePromotionalPrice') . ')';

        return "(CASE WHEN $promotionalPrice IS NOT NULL AND $promotionalPrice < $price THEN $promotionalPrice ELSE $price END)";
    }

    /**
     * @param list<object> $results
     * @return list<array<string, mixed>>
     */
    private function rows(array $results, Order $order): array
    {
        $purchasables = $this->purchasablesById($results, $order);
        $currency = $order->getStore()->getCurrency();
        $rows = [];

        foreach ($results as $row) {
            $purchasable = $purchasables[$row->id] ?? null;

            if (!$purchasable) {
                continue;
            }

            $available = app(Purchasables::class)->isPurchasableAvailable($purchasable, $order);
            $salePrice = $purchasable->getSalePrice();

            $rows[] = [
                'id' => (int)$row->id,
                'type' => $row->type,
                'typeLabel' => $row->type::displayName(),
                'description' => $row->description,
                'sku' => $row->sku,
                'price' => $salePrice,
                'priceAsCurrency' => Money::toString(Money::toMoney(['value' => $salePrice, 'currency' => $currency])),
                'stock' => $this->stock($purchasable),
                'isAvailable' => $available,
                'unavailableReason' => $available ? null : $this->unavailableReason($purchasable),
            ];
        }

        return $rows;
    }

    /**
     * Loads each purchasable the way a line item does, so the price shown here is the price it gets.
     *
     * @param list<object> $results
     * @return array<int, PurchasableInterface>
     */
    private function purchasablesById(array $results, Order $order): array
    {
        $usesSales = !app(CatalogPricingRules::class)->canUseCatalogPricingRules();
        $purchasables = [];

        foreach ($results as $row) {
            $purchasable = app(Purchasables::class)->getPurchasableById((int)$row->id, $order->orderSiteId, $order->getPricingCustomerId());

            if ($usesSales && $purchasable instanceof Purchasable) {
                $purchasable->loadSales($order);
            }

            if ($purchasable) {
                $purchasables[(int)$row->id] = $purchasable;
            }
        }

        return $purchasables;
    }

    private function stock(PurchasableInterface $purchasable): ?int
    {
        if (!$purchasable instanceof Purchasable || !$purchasable::hasInventory() || !$purchasable->inventoryTracked) {
            return null;
        }

        return $purchasable->getStock();
    }

    private function unavailableReason(PurchasableInterface $purchasable): string
    {
        if (!$purchasable instanceof Purchasable) {
            return t('Not available.', category: 'commerce');
        }

        if (!$purchasable->availableForPurchase) {
            return t('Not available for purchase.', category: 'commerce');
        }

        if ($purchasable->getStatus() !== Purchasable::STATUS_ENABLED) {
            return t('Disabled.', category: 'commerce');
        }

        if ($purchasable::hasInventory() && $purchasable->inventoryTracked && $purchasable->getStock() < 1) {
            return t('Out of stock.', category: 'commerce');
        }

        return t('Not available.', category: 'commerce');
    }
}
