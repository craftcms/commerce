<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\elements\db\PurchasableQuery;
use craft\commerce\elements\Order;
use CraftCms\Commerce\Catalog\Elements\Variant;
use craft\commerce\Plugin;
use CraftCms\Cms\User\Elements\User;
use craft\events\RegisterComponentTypesEvent;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\Events\PurchasableAvailableEvent;
use CraftCms\Commerce\Purchasable\Events\PurchasableOutOfStockPurchasesAllowedEvent;
use CraftCms\Commerce\Purchasable\Events\PurchasableShippableEvent;
use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery as NewPurchasableQuery;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;
use yii\base\InvalidArgumentException;

#[Singleton]
class Purchasables
{
    public const string EVENT_PURCHASABLE_OUT_OF_STOCK_PURCHASES_ALLOWED = 'allowOutOfStockPurchases';

    public const string EVENT_PURCHASABLE_AVAILABLE = 'purchasableAvailable';

    public const string EVENT_PURCHASABLE_SHIPPABLE = 'purchasableShippable';

    public const string EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES = 'registerPurchasableElementTypes';

    /**
     * Memoization of purchasables by ID to avoid duplicate queries.
     */
    private ?Collection $purchasableById = null;

    /**
     * @throws Throwable
     */
    public function isPurchasableOutOfStockPurchasingAllowed(PurchasableInterface $purchasable, ?Order $order = null, ?User $currentUser = null): bool
    {
        $currentUser ??= \Craft::$app->getUser()->getIdentity();

        $event = new PurchasableOutOfStockPurchasesAllowedEvent(
            purchasable: $purchasable,
            order: $order,
            currentUser: $currentUser,
            /** @phpstan-ignore-next-line */
            outOfStockPurchasesAllowed: $purchasable->allowOutOfStockPurchases,
        );

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPurchasables()->hasEventHandlers(self::EVENT_PURCHASABLE_OUT_OF_STOCK_PURCHASES_ALLOWED)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPurchasables()->trigger(self::EVENT_PURCHASABLE_OUT_OF_STOCK_PURCHASES_ALLOWED, $event);
        }

        return $event->outOfStockPurchasesAllowed;
    }

    public function isPurchasableAvailable(PurchasableInterface $purchasable, ?Order $order = null, ?User $currentUser = null): bool
    {
        $currentUser ??= \Craft::$app->getUser()->getIdentity();

        $event = new PurchasableAvailableEvent(
            purchasable: $purchasable,
            isAvailable: $purchasable->getIsAvailable(),
            order: $order,
            currentUser: $currentUser,
        );

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPurchasables()->hasEventHandlers(self::EVENT_PURCHASABLE_AVAILABLE)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPurchasables()->trigger(self::EVENT_PURCHASABLE_AVAILABLE, $event);
        }

        return $event->isAvailable;
    }

    public function isPurchasableShippable(PurchasableInterface $purchasable, ?Order $order = null, ?User $currentUser = null): bool
    {
        $currentUser ??= \Craft::$app->getUser()->getIdentity();

        $event = new PurchasableShippableEvent(
            purchasable: $purchasable,
            isShippable: $purchasable->getIsShippable(),
            order: $order,
            currentUser: $currentUser,
        );

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPurchasables()->hasEventHandlers(self::EVENT_PURCHASABLE_SHIPPABLE)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPurchasables()->trigger(self::EVENT_PURCHASABLE_SHIPPABLE, $event);
        }

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
            $stock = Plugin::getInstance()->getInventory()->getInventoryLevelsForPurchasable($purchasable)->sum('availableTotal');

            DB::table(Table::PURCHASABLES_STORES)
                ->where('purchasableId', $purchasable->id)
                ->where('storeId', $purchasable->getStore()->id)
                ->update(['stock' => $stock]);

            // Since we are updating the stock directly in the database, clear the cache
            \Craft::$app->getElements()->invalidateCachesForElement($purchasable);
        }
    }

    /**
     * Delete a purchasable by its ID.
     *
     * @throws Throwable
     */
    public function deletePurchasableById(int $purchasableId): bool
    {
        $this->purchasableById?->pull($purchasableId);

        return \Craft::$app->getElements()->deleteElementById($purchasableId);
    }

    /**
     * Get a purchasable by its ID.
     */
    public function getPurchasableById(int $purchasableId, ?int $siteId = null, int|false|null $forCustomer = null): ?PurchasableInterface
    {
        // @TODO Verify that returning the memoized purchasable regardless of the requested $siteId / $forCustomer is safe, or scope the cache key by those args
        if ($this->purchasableById !== null && $this->purchasableById->has($purchasableId)) {
            return $this->purchasableById->get($purchasableId);
        }

        $siteId ??= \Craft::$app->getSites()->getCurrentSite()->id;
        $elementType = \Craft::$app->getElements()->getElementTypeById($purchasableId);

        if ($elementType === null || !class_exists($elementType)) {
            return null;
        }

        $query = \Craft::$app->getElements()->createElementQuery($elementType)
            ->id($purchasableId)
            ->siteId($siteId)
            ->status(null)
            ->drafts(null)
            ->provisionalDrafts(null)
            ->revisions(null);

        // Donation (migrated) returns the new PurchasableQuery; Product/Variant (not yet migrated) still return the legacy one.
        if ($query instanceof PurchasableQuery || $query instanceof NewPurchasableQuery) {
            $query->forCustomer($forCustomer);
        }

        $purchasable = $query->one();
        if ($purchasable && !$purchasable instanceof PurchasableInterface) {
            throw new InvalidArgumentException(sprintf('Element %s does not implement %s', $purchasableId, PurchasableInterface::class));
        }

        $this->purchasableById ??= collect();
        $this->purchasableById->put($purchasableId, $purchasable);

        return $purchasable;
    }

    /**
     * Returns all available purchasable element classes.
     *
     * @return string[] The available purchasable element classes.
     */
    public function getAllPurchasableElementTypes(): array
    {
        $purchasableElementTypes = [
            Variant::class,
        ];

        $event = new RegisterComponentTypesEvent(['types' => $purchasableElementTypes]);

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        Plugin::getInstance()->getPurchasables()->trigger(self::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES, $event);

        return $event->types;
    }
}
