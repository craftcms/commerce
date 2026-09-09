<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Plugin;
use Illuminate\Auth\Events\Logout;

class LogoutListener
{
    public function handle(Logout $event): void
    {
        // TODO: fix in Commerce 6.0 - replace Plugin::getInstance() with proper DI (e.g. inject Plugin::class)
        if (!Plugin::getInstance()->isInstalled) {
            return;
        }

        app(Carts::class)->forgetCart();
    }
}
