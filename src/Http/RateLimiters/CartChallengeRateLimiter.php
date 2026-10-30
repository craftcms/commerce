<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\RateLimiters;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class CartChallengeRateLimiter
{
    public const string NAME = 'commerce-cart-challenge';

    public function limit(Request $request): Limit
    {
        return Limit::perSecond(1, 30)->by($request->ip() ?? 'unknown');
    }
}
