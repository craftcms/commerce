<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats\Contracts;

use craft\commerce\models\OrderStatus;
use DateTime;

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

    public function get(): mixed;

    public function getData(): mixed;

    public function getStartDate(): mixed;

    public function getEndDate(): mixed;

    public function setStartDate(?DateTime $date): void;

    public function setEndDate(?DateTime $date): void;

    public function prepareData(mixed $data): mixed;

    public function getDateRangeWording(): string;

    /** @return array|null */
    public function getOrderStatuses(): ?array;

    /**
     * Set order statuses to limit stat query. Accepts OrderStatus models, handle strings, or uid strings.
     *
     * @param OrderStatus[]|string[]|null $orderStatuses
     */
    public function setOrderStatuses(?array $orderStatuses): void;
}
