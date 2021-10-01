<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\UserAddress;
use craft\test\ActiveFixture;

/**
 * User Addresses Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class UserAddressesFixture extends ActiveFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/user-addresses.php';

    /**
     * @inheritdoc
     */
    public $modelClass = UserAddress::class;

    public $depends = [AddressesFixture::class, CustomerFixture::class];
}