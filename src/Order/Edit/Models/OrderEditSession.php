<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_order_edit_sessions` table.
 *
 * It's used internally by {@see \CraftCms\Commerce\Order\Edit\OrderEditSessions}, which hydrates
 * rows into {@see \CraftCms\Commerce\Order\Edit\Data\OrderEditSession} objects.
 *
 * @property int $id
 * @property int $orderId
 * @property int|null $userId
 * @property array<string, mixed> $payload
 * @property \Illuminate\Support\Carbon $dateCreated
 * @property \Illuminate\Support\Carbon $dateUpdated
 */
class OrderEditSession extends BaseModel
{
    use HasUid;

    #[\Override]
    protected $table = Table::ORDER_EDIT_SESSIONS;

    #[\Override]
    protected $casts = [
        'orderId' => 'integer',
        'userId' => 'integer',
        'payload' => 'array',
    ];
}
