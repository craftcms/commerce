<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Middleware;

use Closure;
use CraftCms\Cms\Config\GeneralConfig;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PoweredByHeader
{
    public function __construct(
        private readonly GeneralConfig $generalConfig,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if (!$response instanceof Response || !$this->generalConfig->sendPoweredByHeader) {
            return $response;
        }

        $header = str($response->headers->get('X-Powered-By', ''))
            ->explode(',')
            ->add('Craft Commerce')
            ->unique()
            ->filter()
            ->join(',');

        $response->headers->set('X-Powered-By', $header);

        return $response;
    }
}
