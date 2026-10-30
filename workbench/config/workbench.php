<?php

declare(strict_types=1);

return [
    'cms_assets_path' => env('WORKBENCH_CMS_ASSETS_PATH'),
    'admin_email' => env('WORKBENCH_ADMIN_EMAIL', default: 'admin@example.test'),
    'admin_username' => env('WORKBENCH_ADMIN_USERNAME', default: 'admin'),
    'admin_password' => env('WORKBENCH_ADMIN_PASSWORD'),
];
