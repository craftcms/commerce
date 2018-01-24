<?php

namespace craft\commerce\models\payments;

use craft\base\Model;

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
     * @var string payment currency ISO code
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

    /**
     * @var string URL for an invoice or a detailed view
     */
    public $detailsUrl;
}
