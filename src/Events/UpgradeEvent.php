<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Events;

class UpgradeEvent
{
    public function __construct(
        public array $v3columnMap = [],
        public array $v3tables = [],
    ) {
    }
}
