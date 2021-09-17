<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\Address;
use craft\elements\User;
use yii\base\Event;

/**
 * Class UserAddressEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class UserAddressEvent extends Event
{
    /**
     * @var Address The address model
     */
    public Address $address;

    /**
     * @var User
     */
    public User $user;
}
