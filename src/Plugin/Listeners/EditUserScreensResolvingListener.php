<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\User\Events\EditUserScreensResolving;
use CraftCms\Commerce\Http\Controllers\Users\UsersController;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class EditUserScreensResolvingListener
{
    /**
     * Adds a "Commerce" screen to the Edit User screen for users who can access Commerce and manage orders.
     */
    public function handle(EditUserScreensResolving $event): void
    {
        $currentUser = currentUser();

        if ($currentUser?->can('accessPlugin-commerce') && $currentUser->can('commerce-manageOrders')) {
            $event->screens[UsersController::SCREEN_COMMERCE] = ['label' => t('Commerce', category: 'commerce')];
        }
    }
}
