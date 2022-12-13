<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\test\mockclasses;

use craft\commerce\base\Purchasable as BasePurchasable;
use craft\commerce\models\Store;

/**
 * Purchasable
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 2.1
 */
class Purchasable extends BasePurchasable
{
    public bool $isPromotable = true;

    public float $price = 25.10;

    public function getIsPromotable(): bool
    {
        return $this->isPromotable;
    }

    public function getPrice(string|Store|null $store = null): ?float
    {
        return 25.10;
    }

    public function getSku(): string
    {
        return 'commerce_testing_unique_sku';
    }
}
