<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\subscriptions;

use craft\base\Model;
use craft\commerce\models\Currency;
use DateTime;

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
    public $paymentAmount;

    /**
     * @var Currency payment currency
     */
    public $paymentCurrency;

    /**
     * @var DateTime time of payment in UTC
     */
    public $paymentDate;

    /**
     * @var string the payment reference on gateway
     */
    public $paymentReference;

    /**
     * @var bool whether payment has been collected
     */
    public $paid = false;

    /**
     * @var string the gateway response text
     */
    public $response;
}
