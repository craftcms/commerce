<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Plugin;
use Illuminate\Auth\Events\Logout;

class LogoutListener
{
    public function __construct(private readonly Plugin $plugin)
    {
    }

    public function handle(Logout $event): void
    {
        if (!$this->plugin->isInstalled) {
            return;
        }

        app(Carts::class)->forgetCart();
    }
}
