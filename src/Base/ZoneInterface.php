<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Base;

use CraftCms\Commerce\Address\Conditions\ZoneAddressCondition;

interface ZoneInterface
{
    public function getCpEditUrl(): string;

    public function getCondition(): ZoneAddressCondition;

    public function setCondition(ZoneAddressCondition|string|array|null $condition): void;
}
