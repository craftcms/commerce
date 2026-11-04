<?php

namespace craft\commerce\services;

use craft\commerce\events\PurchasableAvailableEvent;
use craft\commerce\events\PurchasableOutOfStockPurchasesAllowedEvent;
use craft\commerce\events\PurchasableShippableEvent;
use craft\commerce\Plugin;
use craft\elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\Events\PurchasableAvailabilityResolving;

use CraftCms\Commerce\Purchasable\Events\PurchasableOutOfStockPurchasesResolving;
use CraftCms\Commerce\Purchasable\Events\PurchasableShippabilityResolving;
use CraftCms\Commerce\Purchasable\PurchasableTypes;
use CraftCms\Yii2Adapter\Event\TypeRegistryCompatibility;
use Illuminate\Support\Facades\Event;
use Throwable;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Purchasable\Purchasables::class)` instead.
 */
class Purchasables extends Component
{
    public const EVENT_PURCHASABLE_OUT_OF_STOCK_PURCHASES_ALLOWED = \CraftCms\Commerce\Purchasable\Purchasables::EVENT_PURCHASABLE_OUT_OF_STOCK_PURCHASES_ALLOWED;

    public const EVENT_PURCHASABLE_AVAILABLE = \CraftCms\Commerce\Purchasable\Purchasables::EVENT_PURCHASABLE_AVAILABLE;

    public const EVENT_PURCHASABLE_SHIPPABLE = \CraftCms\Commerce\Purchasable\Purchasables::EVENT_PURCHASABLE_SHIPPABLE;

    /** @deprecated in 6.0.0. Use `app(\CraftCms\Commerce\Purchasable\PurchasableTypes::class)->register()` instead. */
    public const EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES = 'registerPurchasableElementTypes';

    /**
     * @throws Throwable
     */
    public function isPurchasableOutOfStockPurchasingAllowed(PurchasableInterface $purchasable, ?Order $order = null, ?User $currentUser = null): bool
    {
        return app(\CraftCms\Commerce\Purchasable\Purchasables::class)->isPurchasableOutOfStockPurchasingAllowed($purchasable, $order, $currentUser);
    }

    public function isPurchasableAvailable(PurchasableInterface $purchasable, ?Order $order = null, ?User $currentUser = null): bool
    {
        return app(\CraftCms\Commerce\Purchasable\Purchasables::class)->isPurchasableAvailable($purchasable, $order, $currentUser);
    }

    public function isPurchasableShippable(PurchasableInterface $purchasable, ?Order $order = null, ?User $currentUser = null): bool
    {
        return app(\CraftCms\Commerce\Purchasable\Purchasables::class)->isPurchasableShippable($purchasable, $order, $currentUser);
    }

    public function updateStoreStockCache(PurchasableInterface $purchasable, bool $allSites = false): void
    {
        app(\CraftCms\Commerce\Purchasable\Purchasables::class)->updateStoreStockCache($purchasable, $allSites);
    }

    /**
     * @throws Throwable
     */
    public function deletePurchasableById(int $purchasableId): bool
    {
        return app(\CraftCms\Commerce\Purchasable\Purchasables::class)->deletePurchasableById($purchasableId);
    }

    public function getPurchasableById(int $purchasableId, ?int $siteId = null, int|false|null $forCustomer = null): ?PurchasableInterface
    {
        return app(\CraftCms\Commerce\Purchasable\Purchasables::class)->getPurchasableById($purchasableId, $siteId, $forCustomer);
    }

    /**
     * @return string[]
     */
    public function getAllPurchasableElementTypes(): array
    {
        return app(\CraftCms\Commerce\Purchasable\Purchasables::class)->getAllPurchasableElementTypes();
    }

    /** @internal */
    public static function finalizeRegistrationEvents(): void
    {
        TypeRegistryCompatibility::reconcile(app(PurchasableTypes::class), \craft\commerce\Plugin::getInstance()->getPurchasables(), self::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES);
    }

    public static function registerEvents(): void
    {
        Event::listen(PurchasableOutOfStockPurchasesResolving::class, static function(PurchasableOutOfStockPurchasesResolving $event) {
            $legacy = Plugin::getInstance()->getPurchasables();
            if ($legacy->hasEventHandlers(self::EVENT_PURCHASABLE_OUT_OF_STOCK_PURCHASES_ALLOWED)) {
                $legacyEvent = new PurchasableOutOfStockPurchasesAllowedEvent([
                    'purchasable' => $event->purchasable,
                    'order' => $event->order,
                    'currentUser' => $event->currentUser,
                    'outOfStockPurchasesAllowed' => $event->outOfStockPurchasesAllowed,
                ]);
                $legacy->trigger(self::EVENT_PURCHASABLE_OUT_OF_STOCK_PURCHASES_ALLOWED, $legacyEvent);
                $event->outOfStockPurchasesAllowed = $legacyEvent->outOfStockPurchasesAllowed;
            }
        });

        Event::listen(PurchasableAvailabilityResolving::class, static function(PurchasableAvailabilityResolving $event) {
            $legacy = Plugin::getInstance()->getPurchasables();
            if ($legacy->hasEventHandlers(self::EVENT_PURCHASABLE_AVAILABLE)) {
                $legacyEvent = new PurchasableAvailableEvent([
                    'purchasable' => $event->purchasable,
                    'order' => $event->order,
                    'currentUser' => $event->currentUser,
                    'isAvailable' => $event->isAvailable,
                ]);
                $legacy->trigger(self::EVENT_PURCHASABLE_AVAILABLE, $legacyEvent);
                $event->isAvailable = $legacyEvent->isAvailable;
            }
        });

        Event::listen(PurchasableShippabilityResolving::class, static function(PurchasableShippabilityResolving $event) {
            $legacy = Plugin::getInstance()->getPurchasables();
            if ($legacy->hasEventHandlers(self::EVENT_PURCHASABLE_SHIPPABLE)) {
                $legacyEvent = new PurchasableShippableEvent([
                    'purchasable' => $event->purchasable,
                    'order' => $event->order,
                    'currentUser' => $event->currentUser,
                    'isShippable' => $event->isShippable,
                ]);
                $legacy->trigger(self::EVENT_PURCHASABLE_SHIPPABLE, $legacyEvent);
                $event->isShippable = $legacyEvent->isShippable;
            }
        });
    }
}
