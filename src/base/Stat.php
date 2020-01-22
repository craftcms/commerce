<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */
namespace craft\commerce\base;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\i18n\Locale;
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
     * @param string $dateRange
     * @param null $startDate
     * @param null $endDate
     * @throws \Exception
     */
    public function __construct(string $dateRange = null, $startDate = null, $endDate = null)
    {
        $this->dateRange = $dateRange;
        if ($this->dateRange && $this->dateRange != self::DATE_RANGE_CUSTOM) {
            $this->_setDates();
        } else {
            $this->setStartDate($startDate);
            $this->setEndDate($endDate);
        }

        $user = Craft::$app->getUser()->getIdentity();
        if ($user) {
            $this->weekStartDay = $user->getPreference('weekStartDay');
        }
    }

    /**
     * @inheritDoc
     */
    public function getHandle(): string
    {
        return $this->_handle;
    }

    /**
     * @return array|false|mixed|null
     * @throws Exception
     */
    public function get()
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
     * @inheritDoc
     */
    public function prepareData($data)
    {
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function setStartDate($date)
    {
        $this->_startDate = $date;
    }

    /**
     * @inheritDoc
     */
    public function setEndDate($date)
    {
        $this->_endDate = $date;
    }

    /**
     * @inheritDoc
     */
    public function getStartDate()
    {
        return $this->_startDate;
    }

    /**
     * @inheritDoc
     */
    public function getEndDate()
    {
        return $this->_endDate;
    }

    /**
     * @inheritDoc
     */
    public function getDateRangeWording(): string
    {
        switch ($this->dateRange) {
            case self::DATE_RANGE_ALL:
            {
                return Plugin::t('All');
                break;
            }
            case self::DATE_RANGE_TODAY:
            {
                return Plugin::t('Today');
                break;
            }
            case self::DATE_RANGE_THISWEEK:
            {
                return Plugin::t('This week');
                break;
            }
            case self::DATE_RANGE_THISMONTH:
            {
                return Plugin::t('This month');
                break;
            }
            case self::DATE_RANGE_THISYEAR:
            {
                return Plugin::t('This year');
                break;
            }
            case self::DATE_RANGE_PAST7DAYS:
            {
                return Plugin::t('Past {num} days', ['num' => 7]);
                break;
            }
            case self::DATE_RANGE_PAST30DAYS:
            {
                return Plugin::t('Past {num} days', ['num' => 30]);
                break;
            }
            case self::DATE_RANGE_PAST90DAYS:
            {
                return Plugin::t('Past {num} days', ['num' => 90]);
                break;
            }
            case self::DATE_RANGE_PASTYEAR:
            {
                return Plugin::t('Past year');
                break;
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
                break;
            }
            default:
            {
                return '';
                break;
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
     * @param string $dateRange
     * @return bool|\DateTime|false
     * @throws \Exception
     */
    private function _getStartDate(string $dateRange)
    {
        if ($dateRange == self::DATE_RANGE_CUSTOM) {
            return false;
        }

        $date = new \DateTime();
        switch ($dateRange) {
            case self::DATE_RANGE_ALL:
            {
                $firstCompletedOrder = (new Query())
                    ->select(['dateOrdered'])
                    ->from(Table::ORDERS)
                    ->where(['isCompleted' => 1])
                    ->orderBy('dateOrdered ASC')
                    ->scalar();

                $date = $firstCompletedOrder ? DateTimeHelper::toDateTime($firstCompletedOrder) : new \DateTime();
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
                $date->setDate($date->format('Y'), 1, 1);
                break;
            }
            case self::DATE_RANGE_PAST7DAYS:
            case self::DATE_RANGE_PAST30DAYS:
            case self::DATE_RANGE_PAST90DAYS:
            {
                $numDays = str_replace(['past', 'Days'], ['P','D'], $dateRange);
                $date = $this->_getEndDate($dateRange);
                $interval = new \DateInterval($numDays);
                $date->sub($interval);
                break;
            }
            case self::DATE_RANGE_PASTYEAR:
            {
                $date = $this->_getEndDate($dateRange);
                $interval = new \DateInterval('P1Y');
                $date->sub($interval);
                $date->add(new \DateInterval('P1D'));
                break;

            }
        }

        $date->setTime(0, 0, 0);
        return $date;
    }

    /**
     * Based on the date range return the end date.
     *
     * @param string $dateRange
     * @return bool|\DateTime|false
     * @throws \Exception
     */
    private function _getEndDate(string $dateRange)
    {
        if ($dateRange == self::DATE_RANGE_CUSTOM) {
            return false;
        }

        $date = new \DateTime();
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
     * @return string|null
     * @throws \Exception
     */
    private function _getCacheKey(): ?string
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

    /**
     * @param string $interval
     * @return array|null
     */
    public function getChartQueryOptionsByInterval(string $interval)
    {
        switch ($interval) {
            case 'month':
            {
                return [
                    'interval' => 'P1M',
                    'dateKeyFormat' => 'nY',
                    'dateLabel' => 'CONCAT(EXTRACT(MONTH FROM [[dateOrdered]]), EXTRACT(YEAR FROM [[dateOrdered]]))',
                    'groupBy' => 'EXTRACT(YEAR FROM [[dateOrdered]]), EXTRACT(MONTH FROM [[dateOrdered]])',
                    'orderBy' => 'EXTRACT(YEAR FROM [[dateOrdered]]) ASC, EXTRACT(MONTH FROM [[dateOrdered]]) ASC',
                ];
                break;
            }
            case 'week':
            {
                $return = [
                    'interval' => 'P1W',
                    'dateKeyFormat' => 'oW',
                    'dateLabel' => 'YEARWEEK([[dateOrdered]], 3)',
                    'groupBy' => 'YEARWEEK([[dateOrdered]], 3)',
                    'orderBy' => 'YEARWEEK([[dateOrdered]], 3) ASC',
                ];

                if (Craft::$app->getDb()->getIsPgsql()) {
                    $return['dateLabel'] = 'TO_CHAR(DATE_TRUNC(\'week\', [[dateOrdered]]), \'YYYYWW\')';
                    $return['groupBy'] = 'TO_CHAR(DATE_TRUNC(\'week\', [[dateOrdered]]), \'YYYYWW\')';
                    $return['orderBy'] = 'TO_CHAR(DATE_TRUNC(\'week\', [[dateOrdered]]), \'YYYYWW\')';
                }

                return $return;
                break;
            }
            case 'day':
            {
                return [
                    'interval' => 'P1D',
                    'dateKeyFormat' => 'Y-m-d',
                    'dateLabel' => 'DATE([[dateOrdered]])',
                    'groupBy' => 'DATE([[dateOrdered]])',
                    'orderBy' => 'DATE([[dateOrdered]])',
                ];
                break;
            }
        }

        return null;
    }

    /**
     * Generate base stat query
     *
     * @return \yii\db\Query
     */
    protected function _createStatQuery()
    {
        return (new Query)
            ->from(Table::ORDERS . ' orders')
            ->where(['>=', 'dateOrdered', Db::prepareDateForDb($this->_startDate)])
            ->andWhere(['<=', 'dateOrdered', Db::prepareDateForDb($this->_endDate)])
            ->andWhere(['isCompleted' => 1]);
    }

    /**
     * @param array $select
     * @param array $resultsDefaults
     * @param null|Query $query
     * @return array|null
     * @throws \Exception
     */
    protected function _createChartQuery(array $select = [], array $resultsDefaults = [], $query = null)
    {
        // Allow the passing in of a custom query in case we need to add extra logic
        $query = $query ?: $this->_createStatQuery();

        $defaults = [];
        $dateRangeInterval = self::DATE_RANGE_INTERVAL[$this->dateRange] ?? null;
        $options = $this->getChartQueryOptionsByInterval($dateRangeInterval);

        if ($this->dateRange != self::DATE_RANGE_CUSTOM && !$options) {
            return null;
        }

        if ($this->dateRange == self::DATE_RANGE_CUSTOM) {
            $interval = date_diff($this->_startDate, $this->_endDate);
            $options = $this->_getCustomDateChartQueryOptions($interval->days);
        }

        $dateKeyDate = DateTimeHelper::toDateTime($this->getStartDate()->format('U'));
        while ($dateKeyDate->format($options['dateKeyFormat']) != $this->getEndDate()->format($options['dateKeyFormat'])) {
            $key = $dateKeyDate->format($options['dateKeyFormat']);

            // Setup default results values
            $tmp = $resultsDefaults;
            $tmp['date'] = $key;

            $defaults[$key] = $tmp;
            $dateKeyDate->add(new \DateInterval($options['interval']));
        }

        // Add defaults to select
        $select[] = new Expression($options['dateLabel'] . ' as date');

        $results = $query
            ->select($select)
            ->groupBy(new Expression($options['groupBy']))
            ->orderBy(new Expression($options['orderBy']))
            ->indexBy('date')
            ->all();

        return array_replace($defaults, $results);
    }

    /**
     * @param int $days
     * @return mixed
     */
    private function _getCustomDateChartQueryOptions(int $days) {
        if ($days > 90) {
            return $this->getChartQueryOptionsByInterval('month');
        }

        if ($days > 27) {
            return $this->getChartQueryOptionsByInterval('week');
        }

        return $this->getChartQueryOptionsByInterval('day');
    }
}