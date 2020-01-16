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
    // Constants
    // =========================================================================

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

    public const CHART_QUERY_OPTIONS = [
        self::DATE_RANGE_TODAY => [
            'interval' => 'P1D',
            'dateKeyFormat' => 'Y-m-d',
            'dateLabel' => 'DATE([[dateOrdered]])',
            'groupBy' => 'DATE([[dateOrdered]])',
        ],
        self::DATE_RANGE_THISWEEK => [
            'interval' => 'P1D',
            'dateKeyFormat' => 'Y-m-d',
            'dateLabel' => 'DATE([[dateOrdered]])',
            'groupBy' => 'DATE([[dateOrdered]])',
        ],
        self::DATE_RANGE_THISMONTH => [
            'interval' => 'P1W',
            'dateKeyFormat' => 'oW',
            'dateLabel' => 'YEARWEEK([[dateOrdered]], 3)',
            'groupBy' => 'YEARWEEK([[dateOrdered]], 3)',
        ],
        self::DATE_RANGE_THISYEAR => [
            'interval' => 'P1M',
            'dateKeyFormat' => 'n Y',
            'dateLabel' => 'CONCAT(MONTH([[dateOrdered]]), " ", YEAR([[dateOrdered]]))',
            'groupBy' => 'YEAR([[dateOrdered]]), MONTH([[dateOrdered]])',
        ],
        self::DATE_RANGE_PAST7DAYS => [
            'interval' => 'P1D',
            'dateKeyFormat' => 'Y-m-d',
            'dateLabel' => 'DATE([[dateOrdered]])',
            'groupBy' => 'DATE([[dateOrdered]])',
        ],
        self::DATE_RANGE_PAST30DAYS => [
            'interval' => 'P1W',
            'dateKeyFormat' => 'oW',
            'dateLabel' => 'YEARWEEK([[dateOrdered]], 3)',
            'groupBy' => 'YEARWEEK([[dateOrdered]], 3)',
        ],
        self::DATE_RANGE_PAST90DAYS => [
            'interval' => 'P1W',
            'dateKeyFormat' => 'oW',
            'dateLabel' => 'YEARWEEK([[dateOrdered]], 3)',
            'groupBy' => 'YEARWEEK([[dateOrdered]], 3)',
            ],
        self::DATE_RANGE_PASTYEAR => [
            'interval' => 'P1M',
            'dateKeyFormat' => 'n Y',
            'dateLabel' => 'CONCAT(MONTH([[dateOrdered]]), " ", YEAR([[dateOrdered]]))',
            'groupBy' => 'YEAR([[dateOrdered]]), MONTH([[dateOrdered]])',
        ],
    ];

    // Public Methods
    // =========================================================================

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
    public function processData($data);

    /**
     * @return string
     */
    public function getDateRangeWording(): string;
}