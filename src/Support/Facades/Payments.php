<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static void processPayment(\CraftCms\Commerce\Order\Elements\Order $order, \CraftCms\Commerce\Payment\Forms\BasePaymentForm $form, string|null $redirect, \CraftCms\Commerce\Payment\Data\Transaction|null $transaction, array|null $redirectData = [])
 * @method static \CraftCms\Commerce\Payment\Data\Transaction captureTransaction(\CraftCms\Commerce\Payment\Data\Transaction $transaction)
 * @method static \CraftCms\Commerce\Payment\Data\Transaction refundTransaction(\CraftCms\Commerce\Payment\Data\Transaction $transaction, float|null $amount = null, string $note = '')
 * @method static bool completePayment(\CraftCms\Commerce\Payment\Data\Transaction $transaction, string|null $customError)
 *
 * @see \CraftCms\Commerce\Payment\Payments
 */
class Payments extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Payment\Payments::class;
    }
}
