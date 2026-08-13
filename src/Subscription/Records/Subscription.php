<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_subscriptions` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Subscription\Subscriptions} and
 * {@see \CraftCms\Commerce\Http\Controllers\SubscriptionsController} to read/write rows.
 *
 * The still-legacy {@see \craft\commerce\elements\Subscription} element uses its own
 * `craft\commerce\records\Subscription` (the same table) for its element/type-table pairing —
 * this class is a read/update-only sibling for `src/`, not a replacement for that pairing.
 */
class Subscription extends BaseModel
{
    #[\Override]
    protected $table = Table::SUBSCRIPTIONS;

    #[\Override]
    protected $casts = [
        'gatewayId' => 'integer',
        'orderId' => 'integer',
        'planId' => 'integer',
        'userId' => 'integer',
        'trialDays' => 'integer',
        'isCanceled' => 'boolean',
        'isExpired' => 'boolean',
        'isSuspended' => 'boolean',
        'hasStarted' => 'boolean',
        'dateCanceled' => 'datetime',
        'dateExpired' => 'datetime',
        'dateSuspended' => 'datetime',
        'nextPaymentDate' => 'datetime',
        'subscriptionData' => 'array',
    ];
}
