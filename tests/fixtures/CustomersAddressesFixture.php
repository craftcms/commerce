<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\records\CustomerAddress;
use craft\test\Fixture;

/**
 * Customers Addresses Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class CustomersAddressesFixture extends Fixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__.'/data/customers-addresses.php';

    /**
     * @inheritdoc
     */
    public $modelClass = CustomerAddress::class;

    public $depends = [AddressesFixture::class, CustomerFixture::class];
}