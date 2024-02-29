<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use craft\helpers\Json;

/**
 * Line item helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.1
 */
class LineItem
{
    /**
     * @return string The generated options signature
     */
    public static function generateOptionsSignature(array $options = [], ?int $lineItemId = null): string
    {
        if ($lineItemId) {
            $options['lineItemId'] = $lineItemId;
        }
        ksort($options);
        return md5(Json::encode($options));
    }
}
