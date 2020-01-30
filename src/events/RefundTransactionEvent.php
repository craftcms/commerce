<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

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
}
