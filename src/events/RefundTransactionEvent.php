<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\Transaction;

/**
 * Class RefundTransactionEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class RefundTransactionEvent extends TransactionEvent
{
    /**
     * @var float The amount to refund
     */
    public $amount;

    /**
     * @var Transaction The transaction created which is the refund
     */
    public $refundTransaction;
}
