<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Middleware;

use Closure;
use CraftCms\Cms\Support\Facades\Deprecator;
use Illuminate\Http\Request;

class LogDeprecatedAction
{
    public static function replacedBy(string $deprecated, string $replacement): string
    {
        return self::class . ":$deprecated,$replacement";
    }

    public function handle(Request $request, Closure $next, string $deprecated, string $replacement): mixed
    {
        Deprecator::log($deprecated, "The `$deprecated` action has been deprecated. Use `$replacement` instead.");

        return $next($request);
    }
}
