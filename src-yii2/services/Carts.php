<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use craft\events\ModelEvent;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Carts::class)` instead.
 */
class Carts extends Component
{
    public const EVENT_BEFORE_PURGE_INACTIVE_CARTS = \CraftCms\Commerce\Services\Carts::EVENT_BEFORE_PURGE_INACTIVE_CARTS;

    /**
     * @see \CraftCms\Commerce\Services\Carts::$cartCookie
     */
    public function getCartCookie(): array
    {
        return app(\CraftCms\Commerce\Services\Carts::class)->cartCookie;
    }

    public function setCartCookie(array $value): void
    {
        app(\CraftCms\Commerce\Services\Carts::class)->cartCookie = $value;
    }

    /**
     * @see \CraftCms\Commerce\Services\Carts::$cartCookieDuration
     */
    public function getCartCookieDuration(): int
    {
        return app(\CraftCms\Commerce\Services\Carts::class)->cartCookieDuration;
    }

    public function setCartCookieDuration(int $value): void
    {
        app(\CraftCms\Commerce\Services\Carts::class)->cartCookieDuration = $value;
    }

    public function getCart(bool $forceSave = false): Order
    {
        return app(\CraftCms\Commerce\Services\Carts::class)->getCart($forceSave);
    }

    public function peekCart(): ?Order
    {
        return app(\CraftCms\Commerce\Services\Carts::class)->peekCart();
    }

    public function forgetCart(): void
    {
        app(\CraftCms\Commerce\Services\Carts::class)->forgetCart();
    }

    public function generateCartNumber(): string
    {
        return app(\CraftCms\Commerce\Services\Carts::class)->generateCartNumber();
    }

    public function getActiveCartEdgeDuration(): string
    {
        return app(\CraftCms\Commerce\Services\Carts::class)->getActiveCartEdgeDuration();
    }

    public function getHasSessionCartNumber(): bool
    {
        return app(\CraftCms\Commerce\Services\Carts::class)->getHasSessionCartNumber();
    }

    public function setSessionCartNumber(string $cartNumber): void
    {
        app(\CraftCms\Commerce\Services\Carts::class)->setSessionCartNumber($cartNumber);
    }

    public function getLoadCartUrl(Order $cart): string
    {
        return app(\CraftCms\Commerce\Services\Carts::class)->getLoadCartUrl($cart);
    }

    public function restorePreviousCartForCurrentUser(): void
    {
        app(\CraftCms\Commerce\Services\Carts::class)->restorePreviousCartForCurrentUser();
    }

    public function purgeIncompleteCarts(): int
    {
        return app(\CraftCms\Commerce\Services\Carts::class)->purgeIncompleteCarts();
    }

    public function afterSaveUserHandler(ModelEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Carts::class)->afterSaveUserHandler($event);
    }
}
