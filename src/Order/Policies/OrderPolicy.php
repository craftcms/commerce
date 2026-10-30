<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Policies;

use CraftCms\Cms\Element\Policies\ElementPolicy;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Store\Stores;

class OrderPolicy extends ElementPolicy
{
    public function view(CraftUser $user, Order $order): bool
    {
        return $user->can('accessPlugin-commerce')
            && $user->can('commerce-manageOrders')
            && $order->storeId !== null
            && app(Stores::class)->getStoresByUserId($user->getCraftUserId())->contains('id', $order->storeId);
    }

    public function save(CraftUser $user, Order $order): bool
    {
        return $this->view($user, $order) && $user->can('commerce-editOrders');
    }

    public function delete(CraftUser $user, Order $order): bool
    {
        return $this->view($user, $order) && $user->can('commerce-deleteOrders');
    }

    public function capture(CraftUser $user, Order $order): bool
    {
        return $this->view($user, $order) && $user->can('commerce-capturePayment');
    }

    public function refund(CraftUser $user, Order $order): bool
    {
        return $this->view($user, $order) && $user->can('commerce-refundPayment');
    }
}
