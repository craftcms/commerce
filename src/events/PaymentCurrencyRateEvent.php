<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\Transaction;
use yii\base\Event;

/**
 * Payment Currency Rate Event
 *
 * @since 5.7.0
 */
class PaymentCurrencyRateEvent extends Event
{
    /**
     * @var float The rate that will be used. Set this to override the rate.
     */
    public float $rate;

    /**
     * @var Transaction|null The transaction the rate is being resolved for, if any.
     */
    public ?Transaction $transaction = null;
}
