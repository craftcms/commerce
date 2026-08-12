<?php

use CraftCms\Commerce\Http\Controllers\WebhooksController;
use Illuminate\Support\Facades\Route;

Route::post('commerce/webhooks/process-webhook/gateway/{gatewayId}', [WebhooksController::class, 'processWebhook'])
    ->whereNumber('gatewayId');
