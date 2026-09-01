<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Commerce\Customer\Customers;
use CraftCms\Commerce\Plugin;
use Illuminate\Auth\Events\Login;

class LoginListener
{
    public function handle(Login $event): void
    {
        if (!Plugin::getInstance()->isInstalled) {
            return;
        }

        app(Customers::class)->loginHandler();
    }
}
