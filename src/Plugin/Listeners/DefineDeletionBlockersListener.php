<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\Element\Events\DefineDeletionBlockers;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Plugin;

class DefineDeletionBlockersListener
{
    public function __construct(private readonly Plugin $plugin)
    {
    }

    public function handle(DefineDeletionBlockers $event): void
    {
        if (!$this->plugin->isInstalled) {
            return;
        }

        if ($event->elementType === User::class) {
            app(Orders::class)->beforeDeleteUserHandler($event);
        }
    }
}
