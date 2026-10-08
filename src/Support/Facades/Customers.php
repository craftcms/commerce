<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static bool savePrimaryShippingAddressId(\CraftCms\Cms\User\Elements\User $user, int|null $addressId)
 * @method static bool savePrimaryBillingAddressId(\CraftCms\Cms\User\Elements\User $user, int|null $addressId)
 * @method static bool savePrimaryPaymentSourceId(\CraftCms\Cms\User\Elements\User $user, int|null $paymentSourceId)
 * @method static void loginHandler()
 * @method static void afterSaveUserHandler(\CraftCms\Cms\Element\Events\ElementSaved $event)
 * @method static void afterSaveAddressHandler(\CraftCms\Cms\Element\Events\ElementSaved $event)
 * @method static void orderCompleteHandler(\CraftCms\Commerce\Order\Elements\Order $order)
 * @method static \CraftCms\Commerce\Order\Elements\Order[] eagerLoadCustomerForOrders(\CraftCms\Commerce\Order\Elements\Order[] $orders)
 * @method static \CraftCms\Commerce\Customer\Models\Customer ensureCustomer(\CraftCms\Cms\User\Elements\User $user)
 * @method static bool transferCustomerData(\CraftCms\Cms\User\Elements\User $fromCustomer, \CraftCms\Cms\User\Elements\User $toCustomer)
 *
 * @see \CraftCms\Commerce\Customer\Customers
 */
class Customers extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Customer\Customers::class;
    }
}
