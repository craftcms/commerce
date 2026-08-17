<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use craft\helpers\ArrayHelper;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Models\OrderStatus;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Translation\Locale;
use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

use function CraftCms\Cms\t;

abstract class Stat implements StatInterface
{
    use StoreTrait;

    public bool $cache = false;

    /**
     * How long to cache the data, in seconds.
     */
    public int $cacheDuration = 0;

    public ?string $dateRange = StatInterface::DATE_RANGE_TODAY;

    public int $weekStartDay = 1; // Monday

    protected string $_handle;

    private ?DateTime $_startDate = null;

    private ?DateTime $_endDate = null;

    private ?string $_cacheKey = null;

    private ?array $_orderStatuses = null;

    public function __construct(?string $dateRange = null, mixed $startDate = null, mixed $endDate = null, ?int $storeId = null)
    {
        $user = \CraftCms\Cms\currentUser();
        if ($user) {
            $this->weekStartDay = $user->getPreference('weekStartDay') ?? $this->weekStartDay;
        }

        $this->dateRange = $dateRange ?? $this->dateRange;
        if ($this->dateRange && $this->dateRange != self::DATE_RANGE_CUSTOM) {
            $this->setDates();
        } else {
            $this->setStartDate($startDate);
            $this->setEndDate($endDate);
        }

        $this->storeId = $storeId ?? $this->storeId;
    }

    #[\Override]
    public function getHandle(): string
    {
        return $this->_handle;
    }

    #[\Override]
    public function get(): mixed
    {
        $this->setDates();

        if (!$this->cache) {
            $data = $this->getData();
            return $this->prepareData($data);
        }

        $this->_cacheKey = $this->getCacheKey();

        if (!$this->_cacheKey) {
            throw new \Exception('Unable to create cache key.');
        }

        if (!Cache::has($this->_cacheKey)) {
            $data = $this->getData();
            Cache::put($this->_cacheKey, $data, $this->cacheDuration);
        } else {
            $data = Cache::get($this->_cacheKey);
        }

        return $this->prepareData($data);
    }

    #[\Override]
    public function prepareData($data): mixed
    {
        return $data;
    }

    #[\Override]
    public function setStartDate(?DateTime $date): void
    {
        if (!$date) {
            $this->_startDate = $this->getFirstCompletedOrderDate();
        } else {
            $this->_startDate = $date;
        }
    }

    #[\Override]
    public function setEndDate(?DateTime $date): void
    {
        if (!$date) {
            $this->_endDate = new DateTime();
        } else {
            $this->_endDate = $date;
        }
    }

    #[\Override]
    public function getStartDate(): mixed
    {
        return $this->_startDate;
    }

    #[\Override]
    public function getEndDate(): mixed
    {
        return $this->_endDate;
    }

    #[\Override]
    public function getDateRangeWording(): string
    {
        switch ($this->dateRange) {
            case self::DATE_RANGE_ALL:
                return t('All', category: 'commerce');
            case self::DATE_RANGE_TODAY:
                return t('Today', category: 'commerce');
            case self::DATE_RANGE_THISWEEK:
                return t('This week', category: 'commerce');
            case self::DATE_RANGE_THISMONTH:
                return t('This month', category: 'commerce');
            case self::DATE_RANGE_THISYEAR:
                return t('This year', category: 'commerce');
            case self::DATE_RANGE_PAST7DAYS:
                return t('Past {num} days', ['num' => 7], category: 'commerce');
            case self::DATE_RANGE_PAST30DAYS:
                return t('Past {num} days', ['num' => 30], category: 'commerce');
            case self::DATE_RANGE_PAST90DAYS:
                return t('Past {num} days', ['num' => 90], category: 'commerce');
            case self::DATE_RANGE_PASTYEAR:
                return t('Past year', category: 'commerce');
            case self::DATE_RANGE_CUSTOM:
                if (!$this->_startDate || !$this->_endDate) {
                    return '';
                }

                $startDate = I18N::getFormatter()->asDate($this->_startDate, Locale::LENGTH_SHORT);
                $endDate = I18N::getFormatter()->asDate($this->_endDate, Locale::LENGTH_SHORT);

                if (\Craft::$app->getLocale()->getOrientation() == 'rtl') {
                    return $endDate . ' - ' . $startDate;
                }

                return $startDate . ' - ' . $endDate;
            default:
                return '';
        }
    }

    /**
     * @throws \Exception
     */
    private function setDates(): void
    {
        if (!$this->dateRange) {
            throw new \Exception('A date range string must be specified to set stat dates.');
        }

        if ($this->_startDate && $this->_endDate) {
            return;
        }

        if ($this->dateRange != self::DATE_RANGE_CUSTOM) {
            $this->setStartDate($this->getStartDateForRange($this->dateRange));
            $this->setEndDate($this->getEndDateForRange($this->dateRange));
        }
    }

    /**
     * Based on the date range return the start date.
     */
    private function getStartDateForRange(string $dateRange): bool|DateTime
    {
        if ($dateRange == self::DATE_RANGE_CUSTOM) {
            return false;
        }

        $date = new DateTime();
        switch ($dateRange) {
            case self::DATE_RANGE_ALL:
                $date = $this->getFirstCompletedOrderDate();
                break;
            case self::DATE_RANGE_THISMONTH:
                $date = DateTimeHelper::toDateTime(strtotime('first day of this month'));
                break;
            case self::DATE_RANGE_THISWEEK:
                if (date('l') != self::START_DAY_INT_TO_DAY[$this->weekStartDay]) {
                    $date = DateTimeHelper::toDateTime(strtotime('last ' . self::START_DAY_INT_TO_DAY[$this->weekStartDay]));
                }
                break;
            case self::DATE_RANGE_THISYEAR:
                $date->setDate((int)$date->format('Y'), 1, 1);
                break;
            case self::DATE_RANGE_PAST7DAYS:
            case self::DATE_RANGE_PAST30DAYS:
            case self::DATE_RANGE_PAST90DAYS:
                $number = str_replace(['past', 'Days'], '', $dateRange);
                // Minus one so we include today as a "past day"
                $number--;
                $date = $this->getEndDateForRange($dateRange);
                $interval = new DateInterval('P' . $number . 'D');
                $date->sub($interval);
                break;
            case self::DATE_RANGE_PASTYEAR:
                $date = $this->getEndDateForRange($dateRange);
                $interval = new DateInterval('P1Y');
                $date->sub($interval);
                $date->modify('first day of next month');
                break;
        }

        $date->setTime(0, 0);
        return $date;
    }

    private function getFirstCompletedOrderDate(): DateTime
    {
        $firstCompletedOrder = DB::table(Table::ORDERS)
            ->where('isCompleted', true)
            ->orderBy('dateOrdered')
            ->value('dateOrdered');

        return $firstCompletedOrder ? DateTimeHelper::toDateTime($firstCompletedOrder) : new DateTime();
    }

    /**
     * Based on the date range return the end date.
     */
    private function getEndDateForRange(string $dateRange): bool|DateTime
    {
        if ($dateRange == self::DATE_RANGE_CUSTOM) {
            return false;
        }

        $date = new DateTime();
        switch ($dateRange) {
            case self::DATE_RANGE_THISMONTH:
                $date = DateTimeHelper::toDateTime(strtotime('last day of this month'));
                break;
            case self::DATE_RANGE_THISWEEK:
                $endDayOfWeek = self::START_DAY_INT_TO_END_DAY[$this->weekStartDay];
                if (date('l') != $endDayOfWeek) {
                    $date = DateTimeHelper::toDateTime(strtotime('next ' . $endDayOfWeek));
                }
                break;
        }

        $date->setTime(23, 59, 59);
        return $date;
    }

    /**
     * Generate cache key.
     */
    private function getCacheKey(): string
    {
        $orderLastUpdatedString = 'never';

        $orderLastUpdated = $this->createStatQuery()
            ->orderByDesc('orders.dateUpdated')
            ->value('orders.dateUpdated');

        if ($orderLastUpdated) {
            $orderLastUpdated = DateTimeHelper::toDateTime($orderLastUpdated);
            $orderLastUpdatedString = $orderLastUpdated->format('Y-m-d-H-i-s');
        }

        return implode('-', [$this->getHandle(), $this->dateRange, $this->_startDate->format('U'), $this->_endDate->format('U'), $orderLastUpdatedString]);
    }

    public function getChartQueryOptionsByInterval(string $interval): ?array
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            // The fallback if timezone can't happen in sql is simply just extract the information from the UTC date stored in `dateOrdered`.
            $timezoneConversionSql = 'dateOrdered';

            if (\craft\helpers\Db::supportsTimeZones()) {
                $timezoneConversionSql = "CONVERT_TZ(dateOrdered, 'UTC', '" . \Craft::$app->getTimeZone() . "')";
            } else {
                \Craft::getLogger()->log('For accurate Commerce statistics it is recommend to make sure you have the timezones table populated. https://craftcms.com/knowledge-base/populating-mysql-mariadb-timezone-tables', \Craft::getLogger()::LEVEL_WARNING, 'commerce');
            }
        } else {
            $timezoneConversionSql = "((dateOrdered AT TIME ZONE 'UTC') AT TIME ZONE '" . \Craft::$app->getTimeZone() . "')";
        }

        switch ($interval) {
            case 'month':
                $sqlExpression = 'CONCAT(EXTRACT(YEAR FROM ' . $timezoneConversionSql . "), '-', EXTRACT(MONTH FROM " . $timezoneConversionSql . '))';
                return [
                    'interval' => 'P1M',
                    'dateKeyFormat' => 'Y-n',
                    'dateKey' => $sqlExpression,
                    'groupBy' => $sqlExpression,
                    'orderBy' => $sqlExpression . ' ASC',
                ];
            case 'day':
                $sqlExpression = 'DATE(' . $timezoneConversionSql . ')';
                return [
                    'interval' => 'P1D',
                    'dateKeyFormat' => 'Y-m-d',
                    'dateKey' => $sqlExpression,
                    'groupBy' => $sqlExpression,
                    'orderBy' => $sqlExpression,
                ];
        }

        return null;
    }

    public function getDateRangeInterval(): string
    {
        if ($this->dateRange == self::DATE_RANGE_CUSTOM) {
            $interval = date_diff($this->_startDate, $this->_endDate);
            return ($interval->days > 90) ? 'month' : 'day';
        }

        return self::DATE_RANGE_INTERVAL[$this->dateRange] ?? 'day';
    }

    #[\Override]
    public function getOrderStatuses(): ?array
    {
        if (empty($this->_orderStatuses)) {
            return $this->_orderStatuses;
        }

        $allOrderStatuses = \craft\commerce\Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();
        foreach ($this->_orderStatuses as $key => $orderStatus) {
            if ($orderStatus instanceof OrderStatus) {
                continue;
            }

            if (!is_string($orderStatus)) {
                unset($this->_orderStatuses[$key]);
                continue;
            }

            $orderStatus = ArrayHelper::firstWhere($allOrderStatuses, fn(OrderStatus $os) => $orderStatus === $os->handle || $orderStatus === $os->uid);
            if (!$orderStatus) {
                unset($this->_orderStatuses[$key]);
                continue;
            }

            $this->_orderStatuses[$key] = $orderStatus;
        }

        return $this->_orderStatuses;
    }

    #[\Override]
    public function setOrderStatuses(?array $orderStatuses): void
    {
        $this->_orderStatuses = $orderStatuses;
    }

    /**
     * Generate base stat query
     */
    protected function createStatQuery(): Builder
    {
        // Make sure the end time is always the last point on that day.
        if ($this->_endDate instanceof DateTime) {
            $this->_endDate->setTime(23, 59, 59);
        }

        if ($this->storeId === null) {
            throw new InvalidArgumentException('The store ID has not been set.');
        }

        $query = DB::table(Table::ORDERS . ' as orders')
            ->join('elements', 'elements.id', '=', 'orders.id')
            ->where('orders.storeId', $this->storeId)
            ->where('dateOrdered', '>=', $this->formatDateForDb($this->_startDate))
            ->where('dateOrdered', '<=', $this->formatDateForDb($this->_endDate))
            ->where('isCompleted', true)
            ->whereNull('elements.dateDeleted');

        $orderStatuses = $this->getOrderStatuses();
        if (!empty($orderStatuses)) {
            $query->join(Table::ORDERSTATUSES . ' as os', 'orders.orderStatusId', '=', 'os.id')
                ->whereIn('os.id', ArrayHelper::getColumn($orderStatuses, 'id'));
        }

        return $query;
    }

    /**
     * @param array $select
     * @param array $resultsDefaults
     * @param Builder|null $query
     */
    protected function createChartQuery(array $select = [], array $resultsDefaults = [], ?Builder $query = null): ?array
    {
        // Allow the passing in of a custom query in case we need to add extra logic
        $query = $query ?: $this->createStatQuery();

        $defaults = [];
        $dateRangeInterval = $this->getDateRangeInterval();
        $options = $this->getChartQueryOptionsByInterval($dateRangeInterval);

        if (!$options) {
            return null;
        }

        $dateKeyDate = DateTimeHelper::toDateTime($this->getStartDate()->format('Y-m-d'), true);
        $endDate = $this->getEndDate();
        while ($dateKeyDate <= $endDate) {
            // If we are looking monthly make sure we get every month by using the 1st day
            if ($dateRangeInterval == 'month') {
                $dateKeyDate->setDate((int)$dateKeyDate->format('Y'), (int)$dateKeyDate->format('n'), 1);
            }

            $key = $dateKeyDate->format($options['dateKeyFormat']);

            // Setup default results values
            $tmp = $resultsDefaults;
            $tmp['datekey'] = $key;

            $defaults[$key] = $tmp;
            $dateKeyDate->add(new DateInterval($options['interval']));
        }

        // Add defaults to select
        $select[] = DB::raw($options['dateKey'] . ' as datekey');
        $results = $query
            ->select($select)
            ->groupByRaw($options['groupBy'])
            ->orderByRaw($options['orderBy'])
            ->get()
            ->keyBy('datekey')
            ->map(fn($row) => (array)$row)
            ->all();

        $return = array_replace($defaults, $results);
        ksort($return, SORT_NATURAL);

        return $return;
    }

    protected function formatDateForDb(DateTime $date): string
    {
        $date = clone $date;
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date->format('Y-m-d H:i:s');
    }
}
