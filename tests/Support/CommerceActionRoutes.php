<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;

/**
 * Commerce's own action routes (routes/actions.php) are registered dynamically, once the
 * Commerce plugin loads during the test's database refresh — which happens after
 * craftcms/yii2-adapter has already registered its own catch-all legacy action route
 * (`{actionTrigger}/{any}`, and its CP-prefixed equivalent). Laravel matches routes in
 * registration order, so without reordering, that catch-all claims every request under the
 * action trigger before any of Commerce's own (more specific) routes get a chance, regardless of
 * whether a legacy controller still exists for the requested action.
 *
 * Call this once a test is ready to dispatch a real HTTP request to one of Commerce's own action
 * routes.
 *
 * This is purely a registration-order accident, not Commerce actually needing anything from
 * yii2-adapter — the same ordering issue is live in production too, this just works around it for
 * tests. See COM-672 for investigating a real fix and removing this helper.
 */
function prioritizeCommerceRoutes(): void
{
    /** @var Router $router */
    $router = app('router');

    $commerceRoutes = [];
    $otherRoutes = [];

    foreach ($router->getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        $controller = $route->getAction('controller');

        // Some routes are registered with a leading `\` on the controller's FQCN and some
        // aren't (depends on whether the array callable passed to Route::get()/post() etc. used
        // a `::class` reference or a literal string), so normalize it away before comparing.
        if (is_string($controller) && str_starts_with(ltrim($controller, '\\'), 'CraftCms\\Commerce\\')) {
            $commerceRoutes[] = $route;
        } else {
            $otherRoutes[] = $route;
        }
    }

    $reordered = new RouteCollection();

    foreach ([...$commerceRoutes, ...$otherRoutes] as $route) {
        $reordered->add($route);
    }

    $router->setRoutes($reordered);
}
