<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

// A&M quick commands.
return [
    [
        'name' => 'Commerce Orders',
        'type' => 'Link',
        'url' => \craft\helpers\UrlHelper::cpUrl('commerce/orders')
    ],
    [
        'name' => 'Commerce Products',
        'type' => 'Link',
        'url' => \craft\helpers\UrlHelper::cpUrl('commerce/products')
    ],
    [
        'name' => 'Commerce Promotions',
        'type' => 'Link',
        'url' => \craft\helpers\UrlHelper::cpUrl('commerce/promotions')
    ],
    [
        'name' => 'Commerce Settings',
        'type' => 'Link',
        'url' => \craft\helpers\UrlHelper::cpUrl('commerce/settings')
    ]
];
