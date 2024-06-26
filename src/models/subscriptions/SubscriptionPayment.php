<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\subscriptions;

use craft\base\Model;
use DateTime;
use Money\Currency;

/**
 * Class SubscriptionPayment
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SubscriptionPayment extends Model
{
    /**
     * @var float payment amount
     */
    public float $paymentAmount;

    /**
     * @var Currency payment currency
     */
    public Currency $paymentCurrency;

    /**
     * @var DateTime time of payment in UTC
     */
    public DateTime $paymentDate;

    /**
     * @var string the payment reference on gateway
     */
    public string $paymentReference;

    /**
     * @var bool whether payment has been collected
     */
    public bool $paid = false;

    /**
     * @var string the gateway response text
     */
    public string $response;
}
