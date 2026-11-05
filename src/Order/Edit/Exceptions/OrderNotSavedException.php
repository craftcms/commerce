<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Edit\Exceptions;

use RuntimeException;

/**
 * An edited order couldn't be saved, so what was done for it along the way is rolled back.
 *
 * @since 6.0.0
 */
final class OrderNotSavedException extends RuntimeException
{
}
