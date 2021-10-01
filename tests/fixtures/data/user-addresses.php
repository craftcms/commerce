<?php

$users = \craft\elements\User::find()->indexBy('email')->all();

return [
    [
        'userId' => $users['customer2@crafttest.com']->id,
        'addressId' => '1000',
    ],
    [
        'userId' => $users['customer1@crafttest.com']->id,
        'addressId' => '1002',
    ],
];