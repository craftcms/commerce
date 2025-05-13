<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

return [
    'discount_with_coupon' => [
        'storeId' => 1, // Primary
        'name' => 'Discount 1',
        'perUserLimit' => '1',
        'totalDiscountUseLimit' => '2',
        'baseDiscount' => '10',
        'perItemDiscount' => '5',
        'percentDiscount' => '15.25',
        'enabled' => true,
        'allCategories' => true,
        'allPurchasables' => true,
        'percentageOffSubject' => 'original',
        'requireCouponCode' => true,
        '_coupons' => [
            [
                'code' => 'discount_1',
                'uses' => 0,
                'maxUses' => null,
            ],
        ],
    ],
];
