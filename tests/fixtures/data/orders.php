<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\commerce\records\OrderStatus;

$variants = Variant::find()->indexBy('sku')->all();

$hctWhiteLineItem = !array_key_exists('hct-white', $variants) ? [] : [
    'purchasableId' => $variants['hct-white']->id,
    'options' => [],
    'qty' => 1,
    'note' => '',
    'taxCategoryId' => 1,
];
$hctBlueLineItem = !array_key_exists('hct-blue', $variants) ? [] : [
    'purchasableId' => $variants['hct-blue']->id,
    'options' => ['giftWrapped' => 'yes'],
    'qty' => 2,
    'note' => '',
    'taxCategoryId' => 1,
];
$orderStatuses = OrderStatus::find()->select(['id', 'handle'])->indexBy('handle')->column();

$yesterday = new DateTime();
$yesterday->sub(new DateInterval('P1D'));
$yesterday->setTime(23, 59, 59);

return [
    'completed-new' => [
        'customerId' => '1000',
        'number' => Plugin::getInstance()->getCarts()->generateCartNumber(),
        'email' => 'support@craftcms.com',
        'billingAddressId' => '1002',
        'shippingAddressId' => '1002',
        'orderStatusId' => $orderStatuses['new'] ?? null,
        '_lineItems' => array_filter([$hctWhiteLineItem, $hctBlueLineItem]),
        '_markAsComplete' => true
    ],
    'completed-new-past' => [
        'customerId' => '1000',
        'number' => Plugin::getInstance()->getCarts()->generateCartNumber(),
        'email' => 'support@craftcms.com',
        'billingAddressId' => '1002',
        'shippingAddressId' => '1002',
        'orderStatusId' => $orderStatuses['new'] ?? null,
        '_lineItems' => array_filter([$hctWhiteLineItem, $hctBlueLineItem]),
        '_markAsComplete' => true,
        '_dateOrdered' => $yesterday,
    ],
    'completed-shipped' => [
        'customerId' => '1000',
        'number' => Plugin::getInstance()->getCarts()->generateCartNumber(),
        'email' => 'support@craftcms.com',
        'billingAddressId' => '1002',
        'shippingAddressId' => '1002',
        'orderStatusId' => $orderStatuses['shipped'] ?? null,
        '_lineItems' => array_filter([$hctWhiteLineItem]),
        '_markAsComplete' => true
    ],
];
