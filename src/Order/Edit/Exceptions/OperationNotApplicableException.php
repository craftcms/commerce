<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Exceptions;

use RuntimeException;

/**
 * Thrown when an order operation can't be applied to the order as it now is, e.g. because it
 * refers to a line item that has since been removed.
 *
 * @since 6.0.0
 */
class OperationNotApplicableException extends RuntimeException
{
    public ?int $operationIndex = null;

    public function forOperation(int $index): self
    {
        $exception = new self($this->getMessage(), previous: $this);
        $exception->operationIndex = $index;

        return $exception;
    }
}
