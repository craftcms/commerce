<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */
namespace craft\commerce\base;

/**
 * Stat Interface
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
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

    /**
     * @return string
     */
    public function getHandle(): string;

    /**
     * @return mixed
     */
    public function get();

    /**
     * @return array|null|false
     */
    public function getData();

    /**
     * @return mixed
     */
    public function getStartDate();

    /**
     * @return mixed
     */
    public function getEndDate();

    /**
     * @param null|\DateTime $date
     * @return mixed
     */
    public function setStartDate($date);

    /**
     * @param null|\DateTime $date
     * @return mixed
     */
    public function setEndDate($date);

    /**
     * @param $data
     * @return mixed
     */
    public function prepareData($data);

    /**
     * @return string
     */
    public function getDateRangeWording(): string;
}