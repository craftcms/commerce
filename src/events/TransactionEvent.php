<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\models\Transaction;
use yii\base\Event;

class TransactionEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var Transaction The transaction model
     */
    public $transaction;
}
