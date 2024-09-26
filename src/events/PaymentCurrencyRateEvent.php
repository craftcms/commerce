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
 * @property ?Transaction $transaction
 * @since 2.0
 */
class PaymentCurrencyRateEvent extends Event
{
	/**
	* @var float The rate the event
	*/
	public float $rate;

	public ?Transaction $transaction = null;
}