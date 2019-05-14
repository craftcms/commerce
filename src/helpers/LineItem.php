<?php

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
     * @param $options
     *
     * @return bool Were line items merged?
     */
    public static function generateOptionsSignature($options)
    {
        ksort($options);
        return md5(Json::encode($options));
    }
}

