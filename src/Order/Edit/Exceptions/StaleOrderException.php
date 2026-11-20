<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Exceptions;

use RuntimeException;

/**
 * Thrown when an order edit is committed against an order that has changed since the edit was
 * made, e.g. because a payment came in or someone else saved it.
 *
 * @since 6.0.0
 */
class StaleOrderException extends RuntimeException
{
}
