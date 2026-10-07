<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Order\Elements\Order getCart(bool $forceSave = false)
 * @method static \CraftCms\Commerce\Order\Elements\Order|null peekCart()
 * @method static void forgetCart()
 * @method static string generateCartNumber()
 * @method static string getActiveCartEdgeDuration()
 * @method static bool getHasSessionCartNumber()
 * @method static void setSessionCartNumber(string $cartNumber)
 * @method static string getLoadCartUrl(\CraftCms\Commerce\Order\Elements\Order $cart)
 * @method static void restorePreviousCartForCurrentUser()
 * @method static int purgeIncompleteCarts()
 * @method static void afterSaveUserHandler(\CraftCms\Cms\Element\Events\ElementSaved $event)
 *
 * @see \CraftCms\Commerce\Order\Carts
 */
class Carts extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Order\Carts::class;
    }
}
