<?php

namespace craft\commerce\models\subscriptions;

use craft\base\Model;
use craft\commerce\models\Currency;

/**
 * Class SubscriptionPayment
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
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
     * @var \DateTime time of payment in UTC
     */
    public $paymentDate;

    /**
     * @var string the payment reference on gateway
     */
    public $paymentReference;
}
