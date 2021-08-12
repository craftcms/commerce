<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\base\Stat;
use DateInterval;
use DateTime;
use DateTimeZone;
use UnitTester;
use yii\base\Exception;

/**
 * StatTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.2
 */
class StatTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var DateTime
     */
    protected DateTime $today;

    /**
     * @var DateTime
     */
    protected DateTime $yesterday;

    /**
     * @dataProvider instantiateDatesDataProvider
     *
     * @param string $dateRange
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @throws Exception
     */
    public function testInstantiateDates(string $dateRange, DateTime $startDate, DateTime $endDate): void
    {
        $stat = $this->_createStatClass($dateRange, $startDate, $endDate);

        $data = $stat->get();

        self::assertArrayHasKey($startDate->format('Y-m-d'), $data);
        self::assertArrayHasKey($endDate->format('Y-m-d'), $data);
        self::assertCount(2, $data);
    }

    /**
     * @dataProvider predefinedDateRangesDataProvider
     *
     * @param string $dateRange
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $keysCount
     * @param bool $keyedByDays
     * @throws Exception
     */
    public function testPredefinedDateRanges(string $dateRange, DateTime $startDate, DateTime $endDate, int $keysCount, bool $keyedByDays = true): void
    {
        $format = $keyedByDays ? 'Y-m-d' : 'Y-n';
        $stat = $this->_createStatClass($dateRange, $startDate, $endDate);

        $data = $stat->get();

        while ($startDate <= $endDate) {
            self::assertArrayHasKey($startDate->format($format), $data);

            if ($keyedByDays) {
                $startDate->add(new DateInterval('P1D'));
            } else {
                $startDate->add(new DateInterval('P1M'));
            }
        }

        self::assertCount($keysCount, $data);
    }

    /**
     * Create an anonymous stat class for testing generic features
     * @param $range
     * @param $start
     * @param $end
     * @return Stat
     */
    private function _createStatClass($range, $start, $end): Stat
    {
        return new class($range, $start, $end) extends Stat {
            // Prevent caching
            public bool $cache = false;

            // Implement getData method
            public function getData()
            {
                return $this->_createChartQuery();
            }
        };
    }

    /**
     * @before createDates
     *
     * @return array
     * @throws \Exception
     */
    public function instantiateDatesDataProvider(): array
    {
        // @TODO figure out how to get this from the test Craft app as it hasn't been instantiated at this point #COM-54
        $tz = new DateTimeZone('America/Los_Angeles');

        return [
            [
                Stat::DATE_RANGE_CUSTOM,
                (new DateTime('yesterday', $tz))->setTime(0, 0),
                (new DateTime('now', $tz))->setTime(0, 0),
            ],
        ];
    }

    /**
     * @before createDates
     *
     * @return array
     * @throws \Exception
     */
    public function predefinedDateRangesDataProvider(): array
    {

        // @TODO figure out how to get this from the test Craft app as it hasn't been instantiated at this point #COM-54
        // Put `tz` into class variable before running the test?

        $tz = new DateTimeZone('America/Los_Angeles');
        $today = (new DateTime('now', $tz))->setTime(0, 0);

        return [
            [
                Stat::DATE_RANGE_TODAY,
                clone $today,
                clone $today,
                1,
            ],
            [
                Stat::DATE_RANGE_PAST7DAYS,
                (new DateTime('6 days ago', $tz))->setTime(0, 0),
                clone $today,
                7,
            ],
            [
                Stat::DATE_RANGE_PAST30DAYS,
                (new DateTime('29 days ago', $tz))->setTime(0, 0),
                clone $today,
                30,
            ],
            [
                Stat::DATE_RANGE_PAST90DAYS,
                (new DateTime('89 days ago', $tz))->setTime(0, 0),
                clone $today,
                90,
            ],
            [
                Stat::DATE_RANGE_PASTYEAR,
                (new DateTime('11 months ago', $tz))->setTime(0, 0),
                clone $today,
                12,
                false,
            ],
            [
                Stat::DATE_RANGE_THISMONTH,
                (new DateTime('now', $tz))->setDate($today->format('Y'), $today->format('n'), 1)->setTime(0, 0),
                clone $today,
                $today->format('t'),
            ],
            [
                Stat::DATE_RANGE_THISWEEK,
                (new DateTime('Monday this week', $tz))->setTime(0, 0),
                clone $today,
                7,
            ],
            [
                Stat::DATE_RANGE_THISYEAR,
                (new DateTime('first day of January ' . $today->format('Y'), $tz))->setTime(0, 0),
                clone $today,
                (int)($today->diff((new DateTime('first day of January ' . $today->format('Y'), $tz))->setTime(0, 0))->format('%m')) + 1,
                false,
            ],
        ];
    }
}
