<?php

use CraftCms\Commerce\Http\Controllers\WebhooksController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'commerce/webhooks/process-webhook/gateway/{gatewayId}', [WebhooksController::class, 'process'])
    ->whereNumber('gatewayId');
