<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\Address;
use craft\commerce\models\Customer;
use yii\base\Event;

/**
 * Class CustomerAddressEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.9
 */
class CustomerAddressEvent extends Event
{
    /**
     * @var Address The address model
     */
    public $address;

    /**
     * @var Customer
     */
    public $customer;
}
