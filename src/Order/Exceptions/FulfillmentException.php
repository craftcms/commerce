<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Exceptions;

use Exception;

/**
 * @since 6.0.0
 */
class FulfillmentException extends Exception
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
