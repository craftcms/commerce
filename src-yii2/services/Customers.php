<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Customer\Records\Customer as CustomerRecord;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Customer\Customers::class)` instead.
 */
class Customers extends Component
{
    public const EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE = \CraftCms\Commerce\Customer\Customers::EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE;

    public function savePrimaryShippingAddressId(User $user, ?int $addressId): bool
    {
        return app(\CraftCms\Commerce\Customer\Customers::class)->savePrimaryShippingAddressId($user, $addressId);
    }

    public function savePrimaryBillingAddressId(User $user, ?int $addressId): bool
    {
        return app(\CraftCms\Commerce\Customer\Customers::class)->savePrimaryBillingAddressId($user, $addressId);
    }

    public function savePrimaryPaymentSourceId(User $user, ?int $paymentSourceId): bool
    {
        return app(\CraftCms\Commerce\Customer\Customers::class)->savePrimaryPaymentSourceId($user, $paymentSourceId);
    }

    public function loginHandler(): void
    {
        app(\CraftCms\Commerce\Customer\Customers::class)->loginHandler();
    }

    public function orderCompleteHandler(Order $order): void
    {
        app(\CraftCms\Commerce\Customer\Customers::class)->orderCompleteHandler($order);
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadCustomerForOrders(array $orders): array
    {
        return app(\CraftCms\Commerce\Customer\Customers::class)->eagerLoadCustomerForOrders($orders);
    }

    public function ensureCustomer(User $user): CustomerRecord
    {
        return app(\CraftCms\Commerce\Customer\Customers::class)->ensureCustomer($user);
    }

    /**
     * @throws ElementNotFoundException
     */
    public function transferCustomerData(User $fromCustomer, User $toCustomer): bool
    {
        return app(\CraftCms\Commerce\Customer\Customers::class)->transferCustomerData($fromCustomer, $toCustomer);
    }
}
