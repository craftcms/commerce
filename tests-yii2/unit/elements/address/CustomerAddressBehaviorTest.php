<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\address;

use Codeception\Test\Unit;
use craft\commerce\behaviors\CustomerAddressBehavior;
use craft\elements\Address;
use craft\elements\User;
use UnitTester;

/**
 * CustomerAddressBehaviorTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.10
 */
class CustomerAddressBehaviorTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    public function testHasPropertiesAndMethods(): void
    {
        $address = \Craft::createObject(['class' => Address::class, 'primaryOwnerId' => User::find()->one()->id]);

        self::assertInstanceOf(CustomerAddressBehavior::class, $address->getBehavior('commerce:address'));
        self::assertArrayHasKey('isPrimaryBilling', $address->toArray());
        self::assertArrayHasKey('isPrimaryShipping', $address->toArray());
    }
}
