<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static void handleChangedFieldLayout(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static void handleDeletedFieldLayout()
 * @method static \CraftCms\Commerce\Order\Elements\Order|null getOrderById(int $id)
 * @method static \CraftCms\Commerce\Order\Elements\Order|null getOrderByNumber(string $number)
 * @method static \CraftCms\Commerce\Order\Elements\Order[]|null getOrdersByCustomer(\CraftCms\Cms\User\Elements\User|int $customer)
 * @method static \CraftCms\Commerce\Order\Elements\Order[]|null getOrdersByEmail(string $email)
 * @method static \CraftCms\Commerce\Order\Elements\Order[] eagerLoadAddressesForOrders(\CraftCms\Commerce\Order\Elements\Order[] $orders)
 * @method static void beforeDeleteUserHandler(\CraftCms\Cms\Element\Events\DefineDeletionBlockers $event)
 * @method static int reassignOrders(int|int[] $oldUserId, int $newUserId)
 * @method static int removeCustomerData(int|int[] $orderIds, array $dataToRemove = ['customerId','email'])
 * @method static void afterSaveAddressHandler(\CraftCms\Cms\Element\Events\ElementSaved $event)
 *
 * @see \CraftCms\Commerce\Order\Orders
 */
class Orders extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Order\Orders::class;
    }
}
