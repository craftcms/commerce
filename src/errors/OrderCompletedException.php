<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\errors;

use yii\base\Exception;

/**
 * Class OrderCompletedException
 *
 * Thrown when an incomplete copy of an order is saved after another request has already completed the order.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.9
 */
class OrderCompletedException extends Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return 'Order already completed';
    }
}
