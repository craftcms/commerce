<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use craft\commerce\base\Stat;
use craft\helpers\DateTimeHelper;
use yii\db\Expression;

/**
 * Total Revenue Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TotalRevenue extends Stat
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected $_handle = 'totalRevenue';

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getData()
    {
        $totalSales = [];

        switch ($this->dateRange) {
            case 'pastYear':
            case 'thisYear':
            {
                $dateLabel = new Expression('CONCAT(MONTH([[dateOrdered]]), " ", YEAR([[dateOrdered]])) as date');
                $groupBy = new Expression('YEAR([[dateOrdered]]), MONTH([[dateOrdered]])');
                $dateKeyFormat = 'n Y';
                $interval = 'P1M';
                break;
            }
            case 'past30Days':
            {
                $dateLabel = new Expression('YEARWEEK([[dateOrdered]], 3) as date');
                $groupBy = new Expression('YEARWEEK([[dateOrdered]], 3)');
                $dateKeyFormat = 'oW';
                $interval = 'P1W';
                break;
            }
            default:
            {
                $dateLabel = new Expression('DATE([[dateOrdered]]) as date');
                $groupBy = new Expression('DATE([[dateOrdered]])');
                $dateKeyFormat = 'Y-m-d';
                $interval = 'P1D';
                break;
            }
        }

        $dateKeyDate = DateTimeHelper::toDateTime($this->getStartDate()->format('U'));
        while ($dateKeyDate->format($dateKeyFormat) != $this->getEndDate()->format($dateKeyFormat)) {
            $dateKeyDate->add(new \DateInterval($interval));
            $key = $dateKeyDate->format($dateKeyFormat);

            $totalSales[$key] = [
                'revenue' => 0,
                'orderCount' => 0,
                'date' => $key,
            ];
        }

        $totalSalesResults = $this->_createStatQuery()
            ->select([
                new Expression('SUM([[total]]) as revenue'),
                new Expression('COUNT([[id]]) as orderCount'),
                $dateLabel,
            ])
            ->groupBy($groupBy)
            ->orderBy('dateOrdered ASC')
            ->indexBy('date')
            ->all();

        $totalSales = array_replace($totalSales, $totalSalesResults);

        return $totalSales;
    }
}