<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\commerce\db\Table;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\i18n\Locale;
use DateInterval;
use DateTime;
use yii\base\Exception;
use yii\db\Expression;

/**
 * Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
abstract class Stat implements StatInterface
{
    use StatTrait;

    /**
     * Stat constructor.
     *
     * @param string|null $dateRange
     * @param DateTime|bool|null $startDate
     * @param DateTime|bool|null $endDate
     * @throws \Exception
     */
    public function __construct(string $dateRange = null, mixed $startDate = null, mixed $endDate = null)
    {
        $this->dateRange = $dateRange ?? $this->dateRange;
        if ($this->dateRange && $this->dateRange != self::DATE_RANGE_CUSTOM) {
            $this->_setDates();
        } else {
            $this->setStartDate($startDate);
            $this->setEndDate($endDate);
        }

        $user = Craft::$app->getUser()->getIdentity();
        if ($user) {
            $this->weekStartDay = $user->getPreference('weekStartDay') ?? $this->weekStartDay;
        }
    }

    /**
     * @inheritdoc
     */
    public function getHandle(): string
    {
        return $this->_handle;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function get(): mixed
    {
        $this->_setDates();

        if (!$this->cache) {
            $data = $this->getData();
            return $this->prepareData($data);
        }

        $this->_cacheKey = $this->_getCacheKey();

        if (!$this->_cacheKey) {
            throw new Exception('Unable to create cache key.');
        }

        $data = Craft::$app->getCache()->get($this->_cacheKey);

        if (!$data) {
            $data = $this->getData();
            Craft::$app->getCache()->set($this->_cacheKey, $data, $this->cacheDuration);
        }

        return $this->prepareData($data);
    }

    /**
     * @inheritdoc
     */
    public function prepareData($data): mixed
    {
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function setStartDate(?DateTime $date): void
    {
        if (!$date) {
            $this->_startDate = $this->_getFirstCompletedOrderDate();
        } else {
            $this->_startDate = $date;
        }
    }

    /**
     * @inheritdoc
     */
    public function setEndDate(?DateTime $date): void
    {
        if (!$date) {
            $this->_endDate = new DateTime();
        } else {
            $this->_endDate = $date;
        }
    }

    /**
     * @inheritdoc
     */
    public function getStartDate(): mixed
    {
        return $this->_startDate;
    }

    /**
     * @inheritdoc
     */
    public function getEndDate(): mixed
    {
        return $this->_endDate;
    }

    /**
     * @inheritdoc
     */
    public function getDateRangeWording(): string
    {
        switch ($this->dateRange) {
            case self::DATE_RANGE_ALL:
            {
                return Craft::t('commerce', 'All');
            }
            case self::DATE_RANGE_TODAY:
            {
                return Craft::t('commerce', 'Today');
            }
            case self::DATE_RANGE_THISWEEK:
            {
                return Craft::t('commerce', 'This week');
            }
            case self::DATE_RANGE_THISMONTH:
            {
                return Craft::t('commerce', 'This month');
            }
            case self::DATE_RANGE_THISYEAR:
            {
                return Craft::t('commerce', 'This year');
            }
            case self::DATE_RANGE_PAST7DAYS:
            {
                return Craft::t('commerce', 'Past {num} days', ['num' => 7]);
            }
            case self::DATE_RANGE_PAST30DAYS:
            {
                return Craft::t('commerce', 'Past {num} days', ['num' => 30]);
            }
            case self::DATE_RANGE_PAST90DAYS:
            {
                return Craft::t('commerce', 'Past {num} days', ['num' => 90]);
            }
            case self::DATE_RANGE_PASTYEAR:
            {
                return Craft::t('commerce', 'Past year');
            }
            case self::DATE_RANGE_CUSTOM:
            {
                if (!$this->_startDate || !$this->_endDate) {
                    return '';
                }

                $startDate = Craft::$app->getFormatter()->asDate($this->_startDate, Locale::LENGTH_SHORT);
                $endDate = Craft::$app->getFormatter()->asDate($this->_endDate, Locale::LENGTH_SHORT);

                if (Craft::$app->getLocale()->getOrientation() == 'rtl') {
                    return $endDate . ' - ' . $startDate;
                }

                return $startDate . ' - ' . $endDate;
            }
            default:
            {
                return '';
            }
        }
    }

    /**
     * @throws Exception
     */
    private function _setDates(): void
    {
        if (!$this->dateRange) {
            throw new Exception('A date range string must be specified to set stat dates.');
        }

        if ($this->_startDate && $this->_endDate) {
            return;
        }

        if ($this->dateRange != self::DATE_RANGE_CUSTOM) {
            $this->setStartDate($this->_getStartDate($this->dateRange));
            $this->setEndDate($this->_getEndDate($this->dateRange));
        }
    }

    /**
     * Based on the date range return the start date.
     *
     * @throws \Exception
     */
    private function _getStartDate(string $dateRange): bool|DateTime
    {
        if ($dateRange == self::DATE_RANGE_CUSTOM) {
            return false;
        }

        $date = new DateTime();
        switch ($dateRange) {
            case self::DATE_RANGE_ALL:
            {
                $date = $this->_getFirstCompletedOrderDate();
                break;
            }
            case self::DATE_RANGE_THISMONTH:
            {
                $date = DateTimeHelper::toDateTime(strtotime('first day of this month'));
                break;
            }
            case self::DATE_RANGE_THISWEEK:
            {
                if (date('l') != self::START_DAY_INT_TO_DAY[$this->weekStartDay]) {
                    $date = DateTimeHelper::toDateTime(strtotime('last ' . self::START_DAY_INT_TO_DAY[$this->weekStartDay]));
                }
                break;
            }
            case self::DATE_RANGE_THISYEAR:
            {
                $date->setDate((int)$date->format('Y'), 1, 1);
                break;
            }
            case self::DATE_RANGE_PAST7DAYS:
            case self::DATE_RANGE_PAST30DAYS:
            case self::DATE_RANGE_PAST90DAYS:
            {
                $number = str_replace(['past', 'Days'], '', $dateRange);
                // Minus one so we include today as a "past day"
                $number--;
                $date = $this->_getEndDate($dateRange);
                $interval = new DateInterval('P' . $number . 'D');
                $date->sub($interval);
                break;
            }
            case self::DATE_RANGE_PASTYEAR:
            {
                $date = $this->_getEndDate($dateRange);
                $interval = new DateInterval('P1Y');
                $date->sub($interval);
                $date->modify('first day of next month');
                break;
            }
        }

        $date->setTime(0, 0);
        return $date;
    }

    /**
     * @throws \Exception
     */
    private function _getFirstCompletedOrderDate(): DateTime|false
    {
        $firstCompletedOrder = (new Query())
            ->select(['dateOrdered'])
            ->from(Table::ORDERS)
            ->where(['isCompleted' => true])
            ->orderBy('dateOrdered ASC')
            ->scalar();

        return $firstCompletedOrder ? DateTimeHelper::toDateTime($firstCompletedOrder) : new DateTime();
    }

    /**
     * Based on the date range return the end date.
     *
     * @throws \Exception
     */
    private function _getEndDate(string $dateRange): bool|DateTime
    {
        if ($dateRange == self::DATE_RANGE_CUSTOM) {
            return false;
        }

        $date = new DateTime();
        switch ($dateRange) {
            case self::DATE_RANGE_THISMONTH:
            {
                $date = DateTimeHelper::toDateTime(strtotime('last day of this month'));
                break;
            }
            case self::DATE_RANGE_THISWEEK:
            {
                $endDayOfWeek = self::START_DAY_INT_TO_END_DAY[$this->weekStartDay];
                if (date('l') != $endDayOfWeek) {
                    $date = DateTimeHelper::toDateTime(strtotime('next ' . $endDayOfWeek));
                }
                break;
            }
        }

        $date->setTime(23, 59, 59);
        return $date;
    }

    /**
     * Generate cache key.
     *
     * @throws \Exception
     */
    private function _getCacheKey(): string
    {
        $orderLastUpdatedString = 'never';

        $orderLastUpdated = $this->_createStatQuery()
            ->select(['dateUpdated'])
            ->orderBy('dateUpdated DESC')
            ->scalar();

        if ($orderLastUpdated) {
            $orderLastUpdated = DateTimeHelper::toDateTime($orderLastUpdated);
            $orderLastUpdatedString = $orderLastUpdated->format('Y-m-d-H-i-s');
        }

        return implode('-', [$this->getHandle(), $this->dateRange, $this->_startDate->format('U'), $this->_endDate->format('U'), $orderLastUpdatedString]);
    }

    public function getChartQueryOptionsByInterval(string $interval): ?array
    {
        if (Craft::$app->getDb()->getIsMysql()) {
            // The fallback if timezone can't happen in sql is simply just extract the information from the UTC date stored in `dateOrdered`.
            $timezoneConversionSql = "[[dateOrdered]]";

            if (Db::supportsTimeZones()) {
                $timezoneConversionSql = "CONVERT_TZ([[dateOrdered]], 'UTC', '" . Craft::$app->getTimeZone() . "')";
            } else {
                Craft::getLogger()->log('For accurate Commerce statistics it is recommend to make sure you have the timezones table populated. https://craftcms.com/knowledge-base/populating-mysql-mariadb-timezone-tables', Craft::getLogger()::LEVEL_WARNING, 'commerce');
            }
        } else {
            $timezoneConversionSql = "(([[dateOrdered]] AT TIME ZONE 'UTC') AT TIME ZONE '" . Craft::$app->getTimeZone() . "')";
        }

        switch ($interval) {
            case 'month':
            {
                $sqlExpression = "CONCAT(EXTRACT(YEAR FROM " . $timezoneConversionSql . "), '-', EXTRACT(MONTH FROM " . $timezoneConversionSql . "))";
                return [
                    'interval' => 'P1M',
                    'dateKeyFormat' => 'Y-n',
                    'dateKey' => $sqlExpression,
                    'groupBy' => $sqlExpression,
                    'orderBy' => $sqlExpression . ' ASC',
                ];
            }
            case 'day':
            {
                $sqlExpression = "DATE(" . $timezoneConversionSql . ")";
                return [
                    'interval' => 'P1D',
                    'dateKeyFormat' => 'Y-m-d',
                    'dateKey' => $sqlExpression,
                    'groupBy' => $sqlExpression,
                    'orderBy' => $sqlExpression,
                ];
            }
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

    /**
     * Generate base stat query
     */
    protected function _createStatQuery(): \yii\db\Query
    {
        // Make sure the end time is always the last point on that day.
        if ($this->_endDate instanceof DateTime) {
            $this->_endDate->setTime(23, 59, 59);
        }

        return (new Query())
            ->from(Table::ORDERS . ' orders')
            ->innerJoin('{{%elements}} elements', '[[elements.id]] = [[orders.id]]')
            ->where(['>=', 'dateOrdered', Db::prepareDateForDb($this->_startDate)])
            ->andWhere(['<=', 'dateOrdered', Db::prepareDateForDb($this->_endDate)])
            ->andWhere(['isCompleted' => true])
            ->andWhere(['elements.dateDeleted' => null]);
    }

    /**
     * @param array $select
     * @param array $resultsDefaults
     * @param null|Query $query
     * @return array|null
     * @throws \Exception
     */
    protected function _createChartQuery(array $select = [], array $resultsDefaults = [], ?Query $query = null): ?array
    {
        // Allow the passing in of a custom query in case we need to add extra logic
        $query = $query ?: $this->_createStatQuery();

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
        $select[] = new Expression($options['dateKey'] . ' as datekey');
        $results = $query
            ->select($select)
            ->groupBy(new Expression($options['groupBy']))
            ->orderBy(new Expression($options['orderBy']))
            ->indexBy('datekey')
            ->all();

        $return = array_replace($defaults, $results);
        ksort($return, SORT_NATURAL);

        return $return;
    }
}
