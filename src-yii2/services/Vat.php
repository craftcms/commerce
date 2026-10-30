<?php

namespace craft\commerce\services;

use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Tax\Vat::class)` instead.
 */
class Vat extends Component
{
    public function isValidVatId(string $vatId): bool
    {
        return app(\CraftCms\Commerce\Tax\Vat::class)->isValidVatId($vatId);
    }
}
