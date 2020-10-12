<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use craft\helpers\StringHelper;

/**
 * Purchasable helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class Purchasable
{
    /**
     * Generates a new temporary SKU.
     *
     * @return string
     * @since 3.x
     */
    public static function tempSku(): string
    {
        return '__temp_' . StringHelper::randomString();
    }

    /**
     * Returns whether the given SKU is temporary.
     *
     * @param string $sku
     * @return bool
     * @since 3.x
     */
    public static function isTempSku(string $sku): bool
    {
        return strpos($sku, '__temp_') === 0;
    }
}
