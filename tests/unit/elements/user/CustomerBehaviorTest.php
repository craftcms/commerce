<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\user;

use Codeception\Test\Unit;
use craft\commerce\behaviors\CustomerBehavior;
use craft\elements\User;
use UnitTester;

/**
 * CustomerBehaviorTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.10
 */
class CustomerBehaviorTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    public function testHasPropertiesAndMethods(): void
    {
        $user = User::find()->one();

        self::assertInstanceOf(CustomerBehavior::class, $user->getBehavior('commerce:customer'));
        self::assertArrayHasKey('primaryBillingAddressId', $user->toArray());
        self::assertArrayHasKey('primaryShippingAddressId', $user->toArray());
    }
}
