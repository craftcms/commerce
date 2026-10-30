<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\Listeners;

use CraftCms\Cms\Element\Queries\Events\ElementsHydrated;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Customer\Models\Customer as CustomerRecord;
use CraftCms\Commerce\Support\ObjectState;

class ElementsHydratedListener
{
    public function handle(ElementsHydrated $event): void
    {
        $this->handleUserHydrated($event);
    }

    private function handleUserHydrated($event): void
    {
        $users = collect($event->elements)->filter(static fn($element) => $element instanceof User);

        if ($users->isEmpty()) {
            return;
        }

        $customers = CustomerRecord::whereIn('customerId', $users->pluck('id'))->get();

        foreach ($customers as $customer) {
            $user = $users->firstWhere('id', $customer->customerId);

            if (!$user) {
                continue;
            }

            ObjectState::set($user, 'primaryBillingAddressId', $customer->primaryBillingAddressId);
            ObjectState::set($user, 'primaryShippingAddressId', $customer->primaryShippingAddressId);
        }
    }
}
