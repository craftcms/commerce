<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\models\Discount;
use craft\commerce\Plugin;
use craft\commerce\records\Discount as DiscountRecord;
use craft\commerce\services\Customers;
use craft\commerce\services\Discounts;
use craft\elements\User;
use UnitTester;


/**
 * UserConditionDiscountTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 2.1
 */
class UserConditionDiscountTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;
    
    /**
     *
     */
    protected function _before()
    {
        parent::_before();
        $this->discounts = Plugin::getInstance()->getDiscounts();
    }
    
    public function testIsUserConditionValid()
    {
        $mockCustomers = $this->make(Customers::class, [
            'getUserGroupIdsForUser' => [1, 2]
        ]);
        
        Plugin::getInstance()->set('customers', $mockCustomers);
        
        /** @var Discount $mockDiscount */
        $mockDiscount = $this->make(Discount::class, [
            'getUserGroupIds' => [3, 4]
        ]);
        
        $discountAdjuster = new Discounts();
        
        $mockDiscount->userCondition = DiscountRecord::CONDITION_USERS_ANY_OR_NONE;
        $isValid = $discountAdjuster->isDiscountUserGroupValid($mockDiscount, new User());
        self::assertTrue($isValid);
        
        $mockDiscount->userCondition = DiscountRecord::CONDITION_USERS_INCLUDE_ALL;
        $isValid = $discountAdjuster->isDiscountUserGroupValid($mockDiscount, new User());
        self::assertFalse($isValid);

        /** @var Discount $mockDiscount */
        $mockDiscount = $this->make(Discount::class, [
            'getUserGroupIds' => [2, 3]
        ]);
        
        $mockDiscount->userCondition = DiscountRecord::CONDITION_USERS_INCLUDE_ALL;
        $isValid = $discountAdjuster->isDiscountUserGroupValid($mockDiscount, new User());
        self::assertFalse($isValid);        
        
        $mockDiscount->userCondition = DiscountRecord::CONDITION_USERS_INCLUDE_ANY;

        $isValid = $discountAdjuster->isDiscountUserGroupValid($mockDiscount, new User());
        self::assertTrue($isValid);

        /** @var Discount $mockDiscount */
        $mockDiscount = $this->make(Discount::class, [
            'getUserGroupIds' => [3, 4]
        ]);
        $mockDiscount->userCondition = DiscountRecord::CONDITION_USERS_INCLUDE_ANY;
        $isValid = $discountAdjuster->isDiscountUserGroupValid($mockDiscount, new User());
        self::assertFalse($isValid);

        $mockDiscount->userCondition = DiscountRecord::CONDITION_USERS_EXCLUDE;

        $isValid = $discountAdjuster->isDiscountUserGroupValid($mockDiscount, new User());
        self::assertTrue($isValid);
        
        /** @var Discount $mockDiscount */
        $mockDiscount = $this->make(Discount::class, [
            'getUserGroupIds' => [2, 4]
        ]);
        $mockDiscount->userCondition = DiscountRecord::CONDITION_USERS_EXCLUDE;
        $isValid = $discountAdjuster->isDiscountUserGroupValid($mockDiscount, new User());
        self::assertFalse($isValid);
    }
}
