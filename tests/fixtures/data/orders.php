<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

use craft\commerce\records\OrderStatus;

$variants = \craft\commerce\elements\Variant::find()->indexBy('sku')->all();

$hctWhiteLineItem = !array_key_exists('hct-white', $variants) ? ![] : [
    'purchasbleId' => $variants['hct-white']->id,
    'options' => [],
    'qty' => 1,
    'note' => '',
];
$hctBlueLineItem = !array_key_exists('hct-white', $variants) ? ![] : [
    'purchasbleId' => $variants['hct-blue']->id,
    'options' => ['giftWrapped' => 'yes'],
    'qty' => 2,
    'note' => '',
];
$orderStatuses = OrderStatus::find()->select(['id', 'handle'])->indexBy('handle')->column();

return [
    [
        'customerId' => '1000',
        'email' => 'support@craftcms.com',
        'billingAddressId' => '1002',
        'shippingAddressId' => '1002',
        'orderStatusId' => $orderStatuses['new'] ?? null,
        '_lineItems' => array_filter([$hctWhiteLineItem, $hctBlueLineItem]),
        '_markAsComplete' => false
    ],
    [
        'customerId' => '1000',
        'email' => 'support@craftcms.com',
        'billingAddressId' => '1002',
        'shippingAddressId' => '1002',
        'orderStatusId' => $orderStatuses['shipped'] ?? null,
        '_lineItems' => array_filter([$hctWhiteLineItem]),
        '_markAsComplete' => true
    ],
];
