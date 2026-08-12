<?php

declare(strict_types=1);

namespace CraftCms\Commerce;

use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Route\Routes;
use CraftCms\Cms\User\Events\EditUserScreensResolving;
use CraftCms\Commerce\Http\Controllers\Users\UsersController;
use CraftCms\Commerce\Http\RateLimiters\CartChallengeRateLimiter;
use CraftCms\Commerce\Http\RateLimiters\CartRateLimiter;
use CraftCms\Commerce\Http\RateLimiters\PdfChallengeRateLimiter;
use CraftCms\Commerce\Plugin\Concerns\HasPermissions;
use CraftCms\Commerce\Plugin\Concerns\HasServices;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class Plugin extends BasePlugin
{
    use HasPermissions;
    use HasServices;

    public function register(): void
    {
        // Gateway webhooks and off-site payment returns can't provide a CSRF token
        $routes = app(Routes::class);
        PreventRequestForgery::except([
            'commerce/webhooks/process-webhook/gateway/*',
            $routes->actionTriggerUriPrefix() . '/commerce/webhooks/process-webhook',
            $routes->cpActionTriggerUriPrefix() . '/commerce/webhooks/process-webhook',
            $routes->actionTriggerUriPrefix() . '/commerce/payments/complete-payment',
            $routes->cpActionTriggerUriPrefix() . '/commerce/payments/complete-payment',
        ]);

        RateLimiter::for(CartRateLimiter::NAME, fn(Request $request) => app(CartRateLimiter::class)->limit($request));
        RateLimiter::for(CartChallengeRateLimiter::NAME, fn(Request $request) => app(CartChallengeRateLimiter::class)->limit($request));
        RateLimiter::for(PdfChallengeRateLimiter::NAME, fn(Request $request) => app(PdfChallengeRateLimiter::class)->limit($request));

        // Add a "Commerce" screen to the Edit User screen for users who can access Commerce
        Event::listen(EditUserScreensResolving::class, function(EditUserScreensResolving $event) {
            if (currentUser()?->can('accessPlugin-commerce')) {
                $event->screens[UsersController::SCREEN_COMMERCE] = ['label' => t('Commerce', category: 'commerce')];
            }
        });
    }
}
