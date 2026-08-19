<?php

declare(strict_types=1);

use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Stats\Stat;
use CraftCms\Commerce\Store\Stores;

function createStatClass(?string $dateRange, ?DateTime $start, ?DateTime $end, ?int $storeId): Stat
{
    return new class($dateRange, $start, $end, $storeId) extends Stat {
        public bool $cache = false;

        public function getData(): mixed
        {
            return $this->createChartQuery();
        }
    };
}

test('instantiating with a date range populates the chart with both endpoints', function (string $dateRange, DateTime $startDate, DateTime $endDate) {
    $storeId = app(Stores::class)->getPrimaryStore()->id;
    $stat = createStatClass($dateRange, $startDate, $endDate, $storeId);

    $data = $stat->get();

    expect($data)->toHaveKey($startDate->format('Y-m-d'));
    expect($data)->toHaveKey($endDate->format('Y-m-d'));
    expect($data)->toHaveCount(2);
})->with('instantiateDatesDataProvider');

test('predefined date ranges produce a chart bucket for every day/month in range', function (string $dateRange, DateTime $startDate, DateTime $endDate, int $keysCount, bool $keyedByDays = true) {
    $format = $keyedByDays ? 'Y-m-d' : 'Y-n';
    $storeId = app(Stores::class)->getPrimaryStore()->id;
    $stat = createStatClass($dateRange, $startDate, $endDate, $storeId);

    $data = $stat->get();

    while ($startDate <= $endDate) {
        expect($data)->toHaveKey($startDate->format($format));

        if ($keyedByDays) {
            $startDate->add(new DateInterval('P1D'));
        } else {
            $startDate->add(new DateInterval('P1M'));
        }
    }

    expect($data)->toHaveCount($keysCount);
})->with('predefinedDateRangesDataProvider');

dataset('instantiateDatesDataProvider', function () {
    $tz = new DateTimeZone('America/Los_Angeles');

    return [
        [
            StatInterface::DATE_RANGE_CUSTOM,
            new DateTime('yesterday', $tz)->setTime(0, 0),
            new DateTime('now', $tz)->setTime(0, 0),
        ],
    ];
});

dataset('predefinedDateRangesDataProvider', function () {
    $tz = new DateTimeZone('America/Los_Angeles');
    $today = new DateTime('now', $tz)->setTime(0, 0);

    return [
        StatInterface::DATE_RANGE_TODAY => [
            StatInterface::DATE_RANGE_TODAY,
            clone $today,
            clone $today,
            1,
        ],
        StatInterface::DATE_RANGE_PAST7DAYS => [
            StatInterface::DATE_RANGE_PAST7DAYS,
            new DateTime('6 days ago', $tz)->setTime(0, 0),
            clone $today,
            7,
        ],
        StatInterface::DATE_RANGE_PAST30DAYS => [
            StatInterface::DATE_RANGE_PAST30DAYS,
            new DateTime('29 days ago', $tz)->setTime(0, 0),
            clone $today,
            30,
        ],
        StatInterface::DATE_RANGE_PAST90DAYS => [
            StatInterface::DATE_RANGE_PAST90DAYS,
            new DateTime('89 days ago', $tz)->setTime(0, 0),
            clone $today,
            90,
        ],
        StatInterface::DATE_RANGE_PASTYEAR => [
            StatInterface::DATE_RANGE_PASTYEAR,
            new DateTime('11 months ago', $tz)->setTime(0, 0),
            clone $today,
            12,
            false,
        ],
        StatInterface::DATE_RANGE_THISMONTH => [
            StatInterface::DATE_RANGE_THISMONTH,
            new DateTime('now', $tz)->setDate((int) $today->format('Y'), (int) $today->format('n'), 1)->setTime(0, 0),
            clone $today,
            (int) $today->format('t'),
        ],
        StatInterface::DATE_RANGE_THISWEEK => [
            StatInterface::DATE_RANGE_THISWEEK,
            new DateTime('Monday this week', $tz)->setTime(0, 0),
            clone $today,
            7,
        ],
        StatInterface::DATE_RANGE_THISYEAR => [
            StatInterface::DATE_RANGE_THISYEAR,
            new DateTime('first day of January ' . $today->format('Y'), $tz)->setTime(0, 0),
            clone $today,
            (int) ($today->diff(new DateTime('first day of January ' . $today->format('Y'), $tz)->setTime(0, 0))->format('%m')) + 1,
            false,
        ],
    ];
});
