<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Middleware;

use Closure;
use CraftCms\Commerce\DigitalProduct\DigitalProducts;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides Commerce’s digital products routes until the Digital Products plugin’s data has been migrated.
 */
class RequireDigitalProducts
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(DigitalProducts::class)->isEnabled(), 404);

        return $next($request);
    }
}
