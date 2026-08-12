<?php

use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Commerce\Http\Controllers\DonationsController;
use CraftCms\Commerce\Http\Controllers\Settings\GatewaysController;
use CraftCms\Commerce\Http\Controllers\WebhooksController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/process-webhook', [WebhooksController::class, 'processWebhook']);

// These are also reachable, unauthenticated, at their site-side action URL (per
// CraftCms\Cms\Plugin\Concerns\HasRoutes::registerActionRoutes()) — the `auth`/`can`
// middleware below is what actually protects them, not the URL prefix.
Route::middleware(['auth', 'can:accessPlugin-commerce', 'can:commerce-manageDonationSettings'])
    ->post('donations/save', [DonationsController::class, 'save']);

Route::middleware(['auth', 'can:accessPlugin-commerce', RequireAdmin::class])->group(function () {
    Route::post('gateways/save', [GatewaysController::class, 'save']);
    Route::post('gateways/archive', [GatewaysController::class, 'archive']);
    Route::post('gateways/reorder', [GatewaysController::class, 'reorder']);
});
