<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Customer\Customers;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Plugin;

class ElementSavedListener
{
    public function handle(ElementSaved $event): void
    {
        if (!Plugin::getInstance()->isInstalled) {
            return;
        }

        if ($event->element instanceof User) {
            app(Carts::class)->afterSaveUserHandler($event);
            app(CatalogPricingRules::class)->afterSaveUserHandler($event);
            app(Customers::class)->afterSaveUserHandler($event);
        }

        if ($event->element instanceof Address) {
            app(Orders::class)->afterSaveAddressHandler($event);
            app(Customers::class)->afterSaveAddressHandler($event);
        }
    }
}
