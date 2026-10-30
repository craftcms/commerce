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
        '_userGroups' => [1002],
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
    'customer3' => [
        'active' => true,
        'firstName' => 'Customer',
        'lastName' => 'Three',
        'username' => 'customer3',
        'email' => 'customer3@crafttest.com',
        'fieldLayoutType' => 'craft\elements\User',
    ],
    'inactive-user' => [
        'active' => false,
        'firstName' => 'Inactive',
        'lastName' => 'User',
        'username' => 'inactive-user',
        'email' => 'inactive.user@crafttest.com',
        'fieldLayoutType' => 'craft\elements\User',
    ],
    'credentialed-user' => [
        'active' => true,
        'firstName' => 'Credentialed',
        'lastName' => 'User',
        'username' => 'cred-user',
        'email' => 'cred.user@crafttest.com',
        'newPassword' => 'credUserP455',
        'fieldLayoutType' => 'craft\elements\User',
    ],
];
