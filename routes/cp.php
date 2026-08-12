<?php

use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Commerce\Http\Controllers\DonationsController;
use CraftCms\Commerce\Http\Controllers\Settings\GatewaysController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:accessPlugin-commerce'])->group(function () {
    Route::middleware('can:commerce-manageDonationSettings')
        ->get('commerce/donations', [DonationsController::class, 'edit']);

    Route::middleware(RequireAdmin::class)->group(function () {
        Route::get('commerce/settings/gateways', [GatewaysController::class, 'index']);
        Route::get('commerce/settings/gateways/new', [GatewaysController::class, 'edit']);
        Route::get('commerce/settings/gateways/{id}', [GatewaysController::class, 'edit'])->whereNumber('id');
    });
});
