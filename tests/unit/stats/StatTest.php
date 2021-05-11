<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\base\Stat;
use craft\commerce\stats\TotalRevenue;
use DateTime;
use UnitTester;

/**
 * StatTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class StatTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var DateTime
     */
    protected $today;

    /**
     * @var DateTime
     */
    protected $yesterday;

    /**
     * @dataProvider instantiateDatesDataProvider
     *
     * @param string $dateRange
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @throws \yii\base\Exception
     */
    public function testInstantiateDates(string $dateRange, DateTime $startDate, DateTime $endDate): void
    {
        $stat = $this->_createStatClass($dateRange, $startDate, $endDate);

        $data = $stat->get();

        $this->tester->assertArrayHasKey($startDate->format('Y-m-d'), $data);
        $this->tester->assertArrayHasKey($endDate->format('Y-m-d'), $data);
        $this->tester->assertCount(2, $data);
    }

    /**
     * @dataProvider predefinedDateRangesDataProvider
     *
     * @param $dateRange
     * @param $startDate
     * @param $endDate
     * @param $keysCount
     */
    public function testPredefinedDateRanges($dateRange, $startDate, $endDate, $keysCount, $keyedByDays = true)
    {
        $format = $keyedByDays ? 'Y-m-d' : 'Y-n';
        $stat = $this->_createStatClass($dateRange, $startDate, $endDate);

        $data = $stat->get();

        while ($startDate <= $endDate) {
            $this->tester->assertArrayHasKey($startDate->format($format), $data);

            if ($keyedByDays) {
                $startDate->add(new \DateInterval('P1D'));
            } else {
                $startDate->add(new \DateInterval('P1M'));
            }
        }

        $this->tester->assertCount($keysCount, $data);
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
            public $cache = false;

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
     */
    public function instantiateDatesDataProvider(): array
    {
        // @TODO figure out how to get this from the test Craft app as it hasn't been instantiated at this point
        $tz = new \DateTimeZone('America/Los_Angeles');

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
     */
    public function predefinedDateRangesDataProvider()
    {
        // @TODO figure out how to get this from the test Craft app as it hasn't been instantiated at this point
        $tz = new \DateTimeZone('America/Los_Angeles');
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
