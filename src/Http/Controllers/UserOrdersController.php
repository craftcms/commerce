<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

readonly class UserOrdersController
{
    use RespondsWithFlash;

    public function getOrders(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $user = currentUserElement();

        if (!$user) {
            return $this->asFailure(t('No user authenticated.', category: 'commerce'));
        }

        return $this->asSuccess(data: ['orders' => $user->getOrders()]);
    }
}
