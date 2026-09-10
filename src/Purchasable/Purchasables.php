<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable;

use CraftCms\Cms\Support\Facades\ElementCaches;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\Commerce\Purchasable\Events\PurchasableAvailableEvent;
use CraftCms\Commerce\Purchasable\Events\PurchasableOutOfStockPurchasesAllowedEvent;
use CraftCms\Commerce\Purchasable\Events\PurchasableShippableEvent;
use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use Throwable;
use function CraftCms\Cms\currentUserElement;

#[Singleton]
class Purchasables
{
    public const string EVENT_PURCHASABLE_OUT_OF_STOCK_PURCHASES_ALLOWED = 'allowOutOfStockPurchases';

    public const string EVENT_PURCHASABLE_AVAILABLE = 'purchasableAvailable';

    public const string EVENT_PURCHASABLE_SHIPPABLE = 'purchasableShippable';

    /**
     * Memoization of purchasables by a composite "id-siteId-forCustomer" key, to avoid duplicate queries.
     */
    private ?Collection $purchasableById = null;

    /**
     * @throws Throwable
     */
    public function isPurchasableOutOfStockPurchasingAllowed(PurchasableInterface $purchasable, ?Order $order = null, ?User $currentUser = null): bool
    {
        $currentUser ??= currentUserElement();

        $event = new PurchasableOutOfStockPurchasesAllowedEvent(
            purchasable: $purchasable,
            order: $order,
            currentUser: $currentUser,
            outOfStockPurchasesAllowed: $purchasable->allowOutOfStockPurchases,
        );

        event($event);

        return $event->outOfStockPurchasesAllowed;
    }

    public function isPurchasableAvailable(PurchasableInterface $purchasable, ?Order $order = null, ?User $currentUser = null): bool
    {
        $currentUser ??= currentUserElement();

        $event = new PurchasableAvailableEvent(
            purchasable: $purchasable,
            isAvailable: $purchasable->getIsAvailable(),
            order: $order,
            currentUser: $currentUser,
        );

        event($event);

        return $event->isAvailable;
    }

    public function isPurchasableShippable(PurchasableInterface $purchasable, ?Order $order = null, ?User $currentUser = null): bool
    {
        $currentUser ??= currentUserElement();

        $event = new PurchasableShippableEvent(
            purchasable: $purchasable,
            isShippable: $purchasable->getIsShippable(),
            order: $order,
            currentUser: $currentUser,
        );

        event($event);

        return $event->isShippable;
    }

    /**
     * Updated the cached stock value for the purchasable in a store.
     */
    public function updateStoreStockCache(PurchasableInterface $purchasable, bool $allSites = false): void
    {
        if ($allSites) {
            $purchasables = $purchasable::find()
                ->siteId('*')
                ->id($purchasable->id)
                ->status(null)->all();
        } else {
            $purchasables = [$purchasable];
        }

        /** @var PurchasableInterface $purchasable */
        foreach ($purchasables as $purchasable) {
            if (!$purchasable instanceof Purchasable) {
                continue;
            }

            $stock = app(Inventory::class)->getInventoryLevelsForPurchasable($purchasable)->sum('availableTotal');

            DB::table(Table::PURCHASABLES_STORES)
                ->where('purchasableId', $purchasable->id)
                ->where('storeId', $purchasable->getStore()->id)
                ->update(['stock' => $stock]);

            // Since we are updating the stock directly in the database, clear the cache
            ElementCaches::invalidateForElement($purchasable);
        }
    }

    /**
     * Delete a purchasable by its ID.
     *
     * @throws Throwable
     */
    public function deletePurchasableById(int $purchasableId): bool
    {
        $this->purchasableById?->forget(
            $this->purchasableById->keys()->filter(fn($key) => str_starts_with((string)$key, "$purchasableId-"))->all()
        );

        return Elements::deleteElementById($purchasableId);
    }

    /**
     * Get a purchasable by its ID.
     */
    public function getPurchasableById(int $purchasableId, ?int $siteId = null, int|false|null $forCustomer = null): ?PurchasableInterface
    {
        $siteId ??= Sites::getCurrentSite()->id;
        $cacheKey = sprintf('%d-%d-%s', $purchasableId, $siteId, match (true) {
            $forCustomer === false => 'false',
            $forCustomer === null => 'null',
            default => (string)$forCustomer,
        });

        if ($this->purchasableById !== null && $this->purchasableById->has($cacheKey)) {
            return $this->purchasableById->get($cacheKey);
        }

        $elementType = Elements::getElementTypeById($purchasableId);

        if ($elementType === null || !class_exists($elementType)) {
            return null;
        }

        $query = Elements::createElementQuery($elementType)
            ->id($purchasableId)
            ->siteId($siteId)
            ->status(null)
            ->drafts(null)
            ->provisionalDrafts(null)
            ->revisions(null);

        if ($query instanceof PurchasableQuery) {
            $query->forCustomer($forCustomer);
        }

        $purchasable = $query->one();
        if ($purchasable && !$purchasable instanceof PurchasableInterface) {
            throw new \InvalidArgumentException(sprintf('Element %s does not implement %s', $purchasableId, PurchasableInterface::class));
        }

        $this->purchasableById ??= collect();
        $this->purchasableById->put($cacheKey, $purchasable);

        return $purchasable;
    }

    /**
     * Returns all available purchasable element classes.
     *
     * @return string[] The available purchasable element classes.
     */
    public function getAllPurchasableElementTypes(): array
    {
        return app(PurchasableTypes::class)->types()->all();
    }
}
