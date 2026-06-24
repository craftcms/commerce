<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\elements\User;
use yii\base\Event;

/**
 * Class UpdatePrimaryPaymentSourceEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.8
 */
class UpdatePrimaryPaymentSourceEvent extends Event
{
    /**
     * @var ?int The previous payment source ID
     */
    public ?int $previousPrimaryPaymentSourceId = null;

    /**
     * @var ?int The new payment source ID
     */
    public ?int $newPrimaryPaymentSourceId = null;

    /**
     * @var User The user that the payment source belongs to
     */
    public User $customer;
}
