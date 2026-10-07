<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static bool canCaptureTransaction(\CraftCms\Commerce\Payment\Data\Transaction $transaction)
 * @method static bool canRefundTransaction(\CraftCms\Commerce\Payment\Data\Transaction $transaction)
 * @method static float refundableAmountForTransaction(\CraftCms\Commerce\Payment\Data\Transaction $transaction)
 * @method static \CraftCms\Commerce\Payment\Data\Transaction createTransaction(\CraftCms\Commerce\Order\Elements\Order|null $order = null, \CraftCms\Commerce\Payment\Data\Transaction|null $parentTransaction = null, string|null $typeOverride = null)
 * @method static bool deleteTransactionById(int $id)
 * @method static \CraftCms\Commerce\Payment\Data\Transaction[] getAllTopLevelTransactionsByOrderId(int $orderId)
 * @method static \CraftCms\Commerce\Payment\Data\Transaction[] getAllTransactionsByOrderId(int $orderId)
 * @method static \CraftCms\Commerce\Payment\Data\Transaction[] getChildrenByTransactionId(int $transactionId)
 * @method static \CraftCms\Commerce\Payment\Data\Transaction|null getTransactionByHash(string $hash)
 * @method static \CraftCms\Commerce\Payment\Data\Transaction|null getTransactionByReferenceAndStatus(string $reference, string $status)
 * @method static \CraftCms\Commerce\Payment\Data\Transaction|null getTransactionByReference(string $reference)
 * @method static \CraftCms\Commerce\Payment\Data\Transaction|null getTransactionById(int $id)
 * @method static bool isTransactionSuccessful(\CraftCms\Commerce\Payment\Data\Transaction $transaction)
 * @method static bool saveTransaction(\CraftCms\Commerce\Payment\Data\Transaction $model, bool $runValidation = true)
 * @method static \CraftCms\Commerce\Order\Elements\Order[] eagerLoadTransactionsForOrders(\CraftCms\Commerce\Order\Elements\Order[] $orders)
 *
 * @see \CraftCms\Commerce\Payment\Transactions
 */
class Transactions extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Payment\Transactions::class;
    }
}
