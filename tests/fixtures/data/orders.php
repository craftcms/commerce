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
    'qty' => 4,
    'note' => '',
    'taxCategoryId' => 1,
];
$orderStatuses = OrderStatus::find()->select(['id', 'handle'])->indexBy('handle')->column();

$yesterday = new DateTime();
$yesterday->sub(new DateInterval('P1D'));
$yesterday->setTime(23, 59, 59);

$addresses = [
    'bob' => [
        'firstName' => 'Bob',
        'lastName' => 'Belcher',
        'addressLine1' => '101 Ocean Avenue',
        'addressLine2' => '',
        'locality' => 'Long Island',
        'postalCode' => '12345',
        'title' => 'TV Show',
        'countryCode' => 'US',
        'administrativeArea' => 'NY',
    ],
    'bttf' => [
        'firstName' => 'Emmett',
        'lastName' => 'Brown',
        'addressLine1' => '1640 Riverside Drive',
        'addressLine2' => '',
        'locality' => 'Hill Valley',
        'postalCode' => '88',
        'title' => 'Movies',
        'countryCode' => 'US',
        'administrativeArea' => 'CA',
    ],
    'orphaned' => [
        'firstName' => 'Orphaned Address',
    ],
    'apple' => [
        'firstName' => 'Tim',
        'lastName' => 'Cook',
        'addressLine1' => 'One Apple Park Way',
        'addressLine2' => '',
        'locality' => 'Cupertino',
        'postalCode' => '95014',
        'title' => 'Apple',
        'countryCode' => 'US',
        'administrativeArea' => 'CA',
    ],
];

return [
    'completed-new' => [
        '_customerEmail' => 'customer1@crafttest.com',
        'number' => Plugin::getInstance()->getCarts()->generateCartNumber(),
        'email' => 'support@craftcms.com',
        'orderStatusId' => $orderStatuses['new'] ?? null,
        '_lineItems' => array_filter([$hctWhiteLineItem, $hctBlueLineItem]),
        '_markAsComplete' => true,
        '_billingAddress' => $addresses['apple'],
        '_shippingAddress' => $addresses['apple'],
    ],
    'completed-new-past' => [
        '_customerEmail' => 'customer1@crafttest.com',
        'number' => Plugin::getInstance()->getCarts()->generateCartNumber(),
        'email' => 'support@craftcms.com',
        '_billingAddress' => $addresses['bttf'],
        '_shippingAddress' => $addresses['bttf'],
        'orderStatusId' => $orderStatuses['new'] ?? null,
        '_lineItems' => array_filter([$hctWhiteLineItem, $hctBlueLineItem]),
        '_markAsComplete' => true,
        '_dateOrdered' => $yesterday,
    ],
    'completed-shipped' => [
        '_customerEmail' => 'customer1@crafttest.com',
        'number' => Plugin::getInstance()->getCarts()->generateCartNumber(),
        'email' => 'support@craftcms.com',
        '_billingAddress' => $addresses['bttf'],
        '_shippingAddress' => $addresses['bob'],
        'orderStatusId' => $orderStatuses['shipped'] ?? null,
        '_lineItems' => array_filter([$hctWhiteLineItem]),
        '_markAsComplete' => true,
    ],
];
