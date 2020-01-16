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
 * Class TransactionEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TransactionEvent extends Event
{
    /**
     * @var Transaction The transaction model
     */
    public $transaction;
}
