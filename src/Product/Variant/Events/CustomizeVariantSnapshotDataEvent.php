<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\Events;

use CraftCms\Commerce\Product\Variant\Elements\Variant;
use yii\base\Event;

class CustomizeVariantSnapshotDataEvent extends Event
{
    public function __construct(
        public Variant $variant,
        public array $fieldData,
    ) {
    }
}
