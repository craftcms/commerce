<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_transactions` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Payment\Transactions} to read/write rows, which are then hydrated
 * into (or persisted from) the business {@see \CraftCms\Commerce\Payment\Data\Transaction}
 * object that the rest of the codebase actually works with.
 */
class Transaction extends BaseModel
{
    public const string TYPE_AUTHORIZE = 'authorize';

    public const string TYPE_CAPTURE = 'capture';

    public const string TYPE_PURCHASE = 'purchase';

    public const string TYPE_REFUND = 'refund';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_REDIRECT = 'redirect';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_SUCCESS = 'success';

    public const string STATUS_FAILED = 'failed';

    #[\Override]
    protected $table = Table::TRANSACTIONS;

    #[\Override]
    protected $casts = [
        'orderId' => 'integer',
        'gatewayId' => 'integer',
        'userId' => 'integer',
        'parentId' => 'integer',
        'amount' => 'float',
        'paymentAmount' => 'float',
        'paymentRate' => 'float',
    ];
}
