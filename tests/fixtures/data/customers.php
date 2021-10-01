<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

return [
    'customer1' => [
        'active' => true,
        'username' => 'customer1',
        'email' => 'customer1@crafttest.com',
        'fieldLayoutType' => 'craft\elements\User',
    ],
    'customer2' => [
        'active' => true,
        'firstName' => 'Customer',
        'lastName' => 'Two',
        'username' => 'customer2',
        'email' => 'customer2@crafttest.com',
        'fieldLayoutType' => 'craft\elements\User',
        'field:myTestText' => 'Some test text.',
    ],
];
