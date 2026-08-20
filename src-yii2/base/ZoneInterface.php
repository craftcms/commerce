<?php

namespace craft\commerce\base;

use craft\base\conditions\ConditionInterface;
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;

/**
 * @deprecated use {@see \CraftCms\Commerce\Base\ZoneInterface}
 * @property string $cpEditUrl
 * @property ConditionInterface|string $condition
 */
interface ZoneInterface
{
    public function getCpEditUrl(): string;

    public function getCondition(): ZoneAddressCondition;

    public function setCondition(ZoneAddressCondition|string|array|null $condition): void;
}
