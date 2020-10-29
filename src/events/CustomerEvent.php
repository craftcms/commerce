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
 * @since 3.x
 */
class CustomerEvent extends Event
{
    /**
     * @var Customer
     */
    public $customer;
}
