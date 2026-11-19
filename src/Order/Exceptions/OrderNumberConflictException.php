<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Exceptions;

use Exception;

/**
 * Thrown when a new order is saved with an order number that is already in use.
 */
class OrderNumberConflictException extends Exception
{
    public function __construct(public readonly string $orderNumber)
    {
        parent::__construct("The order number “{$orderNumber}” is already in use.");
    }
}
