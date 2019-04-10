<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\Customer;
use yii\base\Event;

/**
 * Class CustomerEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.1
 */
class CustomerEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var Customer The customer model
     */
    public $customer;

    /**
     * @var bool If this is a new customer
     */
    public $isNew;
}
