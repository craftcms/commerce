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
     * @param array $options
     *
     * @return string The generated options signature
     */
    public static function generateOptionsSignature(array $options = []): string
    {
        ksort($options);
        return md5(Json::encode($options));
    }
}

