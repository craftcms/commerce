<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Exceptions;

use CraftCms\Commerce\Order\Edit\Data\OrderEditSession;
use RuntimeException;

/**
 * Thrown when an admin starts editing an order that another admin is already editing.
 *
 * @since 6.0.0
 */
class OrderBeingEditedException extends RuntimeException
{
    public function __construct(
        public readonly OrderEditSession $session,
        string $message = '',
    ) {
        parent::__construct($message);
    }
}
