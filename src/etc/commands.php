<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

// A&M quick commands.
use craft\helpers\UrlHelper;

return [
    [
        'name' => 'Commerce Orders',
        'type' => 'Link',
        'url' => UrlHelper::cpUrl('commerce/orders')
    ],
    [
        'name' => 'Commerce Products',
        'type' => 'Link',
        'url' => UrlHelper::cpUrl('commerce/products')
    ],
    [
        'name' => 'Commerce Promotions',
        'type' => 'Link',
        'url' => UrlHelper::cpUrl('commerce/promotions')
    ],
    [
        'name' => 'Commerce Settings',
        'type' => 'Link',
        'url' => UrlHelper::cpUrl('commerce/settings')
    ]
];
