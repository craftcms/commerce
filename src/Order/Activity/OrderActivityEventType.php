<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Activity;

use CraftCms\Cms\Activity\ActivityEventType;
use CraftCms\Cms\Activity\Data\ActivitySource;
use CraftCms\Commerce\Plugin;

/**
 * @since 6.0.0
 */
abstract class OrderActivityEventType extends ActivityEventType
{
    public static function source(): ActivitySource
    {
        return ActivitySource::fromPlugin(Plugin::getInstance());
    }
}
