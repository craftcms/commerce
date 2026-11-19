<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Exceptions;

use Exception;

/**
 * Thrown when an incomplete copy of an order is saved after another request has already completed the order.
 */
class OrderCompletedException extends Exception
{
}
