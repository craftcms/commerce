<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Middleware;

use Closure;
use CraftCms\Commerce\License\Licenses;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides Commerce’s license routes until the Digital Products plugin’s data has been migrated.
 */
class RequireLicenses
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(Licenses::class)->isEnabled(), 404);

        return $next($request);
    }
}
