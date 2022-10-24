<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\models\OrderStatus;
use DateTime;

/**
 * Stat Interface
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 * @todo remove ignore: https://github.com/phpstan/phpstan/issues/6778
 * @phpstan-ignore-next-line
 * @mixin StatTrait
 */
interface StatInterface
{
    public const DATE_RANGE_ALL = 'all';
    public const DATE_RANGE_TODAY = 'today';
    public const DATE_RANGE_THISWEEK = 'thisWeek';
    public const DATE_RANGE_THISMONTH = 'thisMonth';
    public const DATE_RANGE_THISYEAR = 'thisYear';
    public const DATE_RANGE_PAST7DAYS = 'past7Days';
    public const DATE_RANGE_PAST30DAYS = 'past30Days';
    public const DATE_RANGE_PAST90DAYS = 'past90Days';
    public const DATE_RANGE_PASTYEAR = 'pastYear';
    public const DATE_RANGE_CUSTOM = 'custom';

    public const START_DAY_INT_TO_DAY = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public const START_DAY_INT_TO_END_DAY = [
        0 => 'Saturday',
        1 => 'Sunday',
        2 => 'Monday',
        3 => 'Tuesday',
        4 => 'Wednesday',
        5 => 'Thursday',
        6 => 'Friday',
    ];

    public const DATE_RANGE_INTERVAL = [
        self::DATE_RANGE_TODAY => 'day',
        self::DATE_RANGE_THISWEEK => 'day',
        self::DATE_RANGE_THISMONTH => 'day',
        self::DATE_RANGE_THISYEAR => 'month',
        self::DATE_RANGE_PAST7DAYS => 'day',
        self::DATE_RANGE_PAST30DAYS => 'day',
        self::DATE_RANGE_PAST90DAYS => 'day',
        self::DATE_RANGE_PASTYEAR => 'month',
        self::DATE_RANGE_ALL => 'month',
    ];

    public function getHandle(): string;

    /**
     * @return mixed
     */
    public function get(): mixed;

    /**
     * @return mixed
     */
    public function getData(): mixed;

    /**
     * @return mixed
     */
    public function getStartDate(): mixed;

    /**
     * @return mixed
     */
    public function getEndDate(): mixed;

    public function setStartDate(?DateTime $date): void;

    public function setEndDate(?DateTime $date): void;

    /**
     * @param $data
     * @return mixed
     */
    public function prepareData($data): mixed;

    public function getDateRangeWording(): string;

    /**
     * @return array|null
     * @since 4.2.0
     */
    public function getOrderStatuses(): ?array;

    /**
     * Set order statuses to limit stat query. Accepts array of `OrderStatus` models, handle strings or uid strings.
     *
     * @param OrderStatus[]|string[]|null $orderStatuses
     * @return void
     * @since 4.2.0
     */
    public function setOrderStatuses(?array $orderStatuses): void;
}
