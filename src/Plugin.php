<?php

declare(strict_types=1);

namespace CraftCms\Commerce;

use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Route\Routes;
use CraftCms\Commerce\Plugin\Concerns\HasPermissions;
use CraftCms\Commerce\Plugin\Concerns\HasServices;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class Plugin extends BasePlugin
{
    use HasPermissions;
    use HasServices;

    public function register(): void
    {
        // Gateway webhooks can't provide a CSRF token
        $routes = app(Routes::class);
        PreventRequestForgery::except([
            'commerce/webhooks/process-webhook/gateway/*',
            $routes->actionTriggerUriPrefix() . '/commerce/webhooks/process-webhook',
            $routes->cpActionTriggerUriPrefix() . '/commerce/webhooks/process-webhook',
        ]);
    }
}
