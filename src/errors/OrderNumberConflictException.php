<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\errors;

use yii\db\IntegrityException;

/**
 * Thrown when a new order is saved with an order number that is already in use.
 *
 * @since 5.7.9
 */
class OrderNumberConflictException extends IntegrityException
{
    /**
     * @param string $orderNumber The conflicting order number
     * @param IntegrityException $previous The database exception
     */
    public function __construct(public readonly string $orderNumber, IntegrityException $previous)
    {
        parent::__construct($previous->getMessage(), $previous->errorInfo, (string)$previous->getCode(), $previous);
    }
}
