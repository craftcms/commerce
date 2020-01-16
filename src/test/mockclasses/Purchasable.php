<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\test\mockclasses;

use craft\commerce\base\Purchasable as BasePurchasable;

/**
 * Purchasable
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 2.1
 */
class Purchasable extends BasePurchasable
{
    public $isPromotable = true;

    /**
     * @return bool
     */
    public function getIsPromotable(): bool
    {
        return $this->isPromotable;
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return 25.10;
    }

    /**
     * @return string
     */
    public function getSku(): string
    {
        return 'commerce_testing_unique_sku';
    }
}
