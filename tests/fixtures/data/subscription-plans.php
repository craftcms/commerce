<?php

return [
    'monthly' => [
        'gatewayId' => 1,
        'name' => 'Monthly Subscription',
        'handle' => 'monthlySubscription',
        'reference' => 'monthly_sub',
        'enabled' => true,
        'planData' => 'dummy.plan',
        'sortOrder' => 1,
    ],
    'weekly-disabled' => [
        'gatewayId' => 1,
        'name' => 'Weekly Subscription',
        'handle' => 'weeklySubscription',
        'reference' => 'weekly_sub',
        'enabled' => false,
        'planData' => 'dummy.plan',
        'sortOrder' => 2,
    ],
];
