<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use CraftCms\Cms\Support\Json;

class LineItem
{
    public static function generateOptionsSignature(array $options = [], ?int $lineItemId = null): string
    {
        if ($lineItemId) {
            $options['lineItemId'] = $lineItemId;
        }
        ksort($options);
        return md5(Json::encode($options));
    }
}
