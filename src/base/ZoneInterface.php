<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\base\conditions\ConditionInterface;
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;

/**
 * @property string $cpEditUrl
 * @property ConditionInterface|string $condition
 */
interface ZoneInterface
{
    /**
     * @return string
     */
    public function getCpEditUrl(): string;

    /**
     * get the zone condition on the zone.
     */
    public function getCondition(): ZoneAddressCondition;

    /**
     * Set the zone condition on the zone.
     *
     * @param ZoneAddressCondition|string|array|null $condition
     * @return void
     */
    public function setCondition(ZoneAddressCondition|string|array|null $condition): void;
}
