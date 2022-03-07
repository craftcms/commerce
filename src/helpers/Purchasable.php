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
 * @since 3.2.8
 */
class Purchasable
{
    const TEMPORARY_SKU_PREFIX = '__temp_';

    /**
     * Generates a new temporary SKU.
     *
     * @since 3.2.8
     */
    public static function tempSku(): string
    {
        return static::TEMPORARY_SKU_PREFIX . StringHelper::randomString();
    }

    /**
     * Returns whether the given SKU is temporary.
     *
     * @since 3.2.8
     */
    public static function isTempSku(string $sku): bool
    {
        return str_starts_with($sku, static::TEMPORARY_SKU_PREFIX);
    }
}
