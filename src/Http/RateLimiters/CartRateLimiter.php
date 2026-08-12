<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\RateLimiters;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

/**
 * Only rate-limits cart requests that explicitly pass a cart number or coupon code — the
 * params most likely to be used for enumeration/brute-force attempts. Requests without either
 * param are left unlimited, matching the legacy `RateLimiter` behavior's conditional `user`
 * callback.
 */
class CartRateLimiter
{
    public const string NAME = 'commerce-cart';

    public function limit(Request $request): Limit
    {
        $isActive = collect(['number', 'couponCode'])->contains(fn($param) => $request->input($param));

        if (!$isActive) {
            return Limit::none();
        }

        return Limit::perSecond(1)->by($request->ip() ?? 'unknown');
    }
}
