<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\models\Address;
use yii\base\Event;

class AddressEvent extends Event
{
    /**
     * @var Address The address model
     */
    public $address;

    /**
     * @var bool If this is a new address
     */
    public $isNew;
}
