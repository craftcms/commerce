<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use craft\events\ModelEvent;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Order\Carts::class)` instead.
 */
class Carts extends Component
{
    public const EVENT_BEFORE_PURGE_INACTIVE_CARTS = \CraftCms\Commerce\Order\Carts::EVENT_BEFORE_PURGE_INACTIVE_CARTS;

    /**
     * @see \CraftCms\Commerce\Order\Carts::$cartCookie
     */
    public function getCartCookie(): array
    {
        return app(\CraftCms\Commerce\Order\Carts::class)->cartCookie;
    }

    public function setCartCookie(array $value): void
    {
        app(\CraftCms\Commerce\Order\Carts::class)->cartCookie = $value;
    }

    /**
     * @see \CraftCms\Commerce\Order\Carts::$cartCookieDuration
     */
    public function getCartCookieDuration(): int
    {
        return app(\CraftCms\Commerce\Order\Carts::class)->cartCookieDuration;
    }

    public function setCartCookieDuration(int $value): void
    {
        app(\CraftCms\Commerce\Order\Carts::class)->cartCookieDuration = $value;
    }

    public function getCart(bool $forceSave = false): Order
    {
        return app(\CraftCms\Commerce\Order\Carts::class)->getCart($forceSave);
    }

    public function peekCart(): ?Order
    {
        return app(\CraftCms\Commerce\Order\Carts::class)->peekCart();
    }

    public function forgetCart(): void
    {
        app(\CraftCms\Commerce\Order\Carts::class)->forgetCart();
    }

    public function generateCartNumber(): string
    {
        return app(\CraftCms\Commerce\Order\Carts::class)->generateCartNumber();
    }

    public function getActiveCartEdgeDuration(): string
    {
        return app(\CraftCms\Commerce\Order\Carts::class)->getActiveCartEdgeDuration();
    }

    public function getHasSessionCartNumber(): bool
    {
        return app(\CraftCms\Commerce\Order\Carts::class)->getHasSessionCartNumber();
    }

    public function setSessionCartNumber(string $cartNumber): void
    {
        app(\CraftCms\Commerce\Order\Carts::class)->setSessionCartNumber($cartNumber);
    }

    public function getLoadCartUrl(Order $cart): string
    {
        return app(\CraftCms\Commerce\Order\Carts::class)->getLoadCartUrl($cart);
    }

    public function restorePreviousCartForCurrentUser(): void
    {
        app(\CraftCms\Commerce\Order\Carts::class)->restorePreviousCartForCurrentUser();
    }

    public function purgeIncompleteCarts(): int
    {
        return app(\CraftCms\Commerce\Order\Carts::class)->purgeIncompleteCarts();
    }

    public function afterSaveUserHandler(ModelEvent $event): void
    {
        app(\CraftCms\Commerce\Order\Carts::class)->afterSaveUserHandler($event);
    }
}
