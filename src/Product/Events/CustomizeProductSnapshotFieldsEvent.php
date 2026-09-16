<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Events;

use CraftCms\Commerce\Product\Elements\Product;
use yii\base\Event;

class CustomizeProductSnapshotFieldsEvent extends Event
{
    public function __construct(
        public Product $product,
        public ?array $fields = null,
    ) {
    }
}
