<?php

namespace craft\commerce\base;

use Craft;
use craft\base\conditions\ConditionInterface;
use craft\base\Model as BaseModel;
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\commerce\records\TaxZone as TaxZoneRecord;
use craft\helpers\Json;
use craft\validators\UniqueValidator;
use DateTime;

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
