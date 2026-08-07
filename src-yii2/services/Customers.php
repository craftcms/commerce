<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use craft\commerce\records\Customer as CustomerRecord;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Customers::class)` instead.
 */
class Customers extends Component
{
    public const EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE = \CraftCms\Commerce\Services\Customers::EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE;

    public function savePrimaryShippingAddressId(User $user, ?int $addressId): bool
    {
        return app(\CraftCms\Commerce\Services\Customers::class)->savePrimaryShippingAddressId($user, $addressId);
    }

    public function savePrimaryBillingAddressId(User $user, ?int $addressId): bool
    {
        return app(\CraftCms\Commerce\Services\Customers::class)->savePrimaryBillingAddressId($user, $addressId);
    }

    public function savePrimaryPaymentSourceId(User $user, ?int $paymentSourceId): bool
    {
        return app(\CraftCms\Commerce\Services\Customers::class)->savePrimaryPaymentSourceId($user, $paymentSourceId);
    }

    public function loginHandler(): void
    {
        app(\CraftCms\Commerce\Services\Customers::class)->loginHandler();
    }

    public function orderCompleteHandler(Order $order): void
    {
        app(\CraftCms\Commerce\Services\Customers::class)->orderCompleteHandler($order);
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadCustomerForOrders(array $orders): array
    {
        return app(\CraftCms\Commerce\Services\Customers::class)->eagerLoadCustomerForOrders($orders);
    }

    public function ensureCustomer(User $user): CustomerRecord
    {
        return app(\CraftCms\Commerce\Services\Customers::class)->ensureCustomer($user);
    }

    /**
     * @throws ElementNotFoundException
     */
    public function transferCustomerData(User $fromCustomer, User $toCustomer): bool
    {
        return app(\CraftCms\Commerce\Services\Customers::class)->transferCustomerData($fromCustomer, $toCustomer);
    }
}
