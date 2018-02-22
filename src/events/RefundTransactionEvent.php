<?php

namespace craft\commerce\events;

/**
 * Class RefundTransactionEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class RefundTransactionEvent extends TransactionEvent
{
    // Properties
    // =========================================================================

    /**
     * @var float The amount to refund
     */
    public $amount;
}
