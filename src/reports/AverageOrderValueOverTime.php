<?php

namespace craft\commerce\reports;

use Craft;
use craft\commerce\base\Report;
use craft\commerce\db\Table;
use craft\db\Query;
use craft\helpers\Db;

class AverageOrderValueOverTime extends Report
{
    /**
     * @inheritDoc
     */
    public function getHandle(): ?string
    {
        return 'averageOrderValueOverTime';
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return Craft::t('commerce', 'Average Order Value Over Time');
    }
    
    /**
     * @inheritDoc
     */
    public function getIcon(): ?string
    {
        return 'chart-line';
    }
    
    /**
     * @inheritDoc
     */
    public function getColumns(): array
    {
        return [
            [
                'label' => Craft::t('commerce', 'Date'),
                'value' => 'date',
                'type' => 'date'
            ],
            [
                'label' => Craft::t('commerce', 'Average Order Value'),
                'value' => 'averageOrderValue',
                'type' => 'money'
            ],
            [
                'label' => Craft::t('commerce', 'Number of Orders'),
                'value' => 'orderCount',
                'type' => 'number'
            ],
            [
                'label' => Craft::t('commerce', 'Total Revenue'),
                'value' => 'totalRevenue',
                'type' => 'money'
            ]
        ];
    }
    
    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return [
            [
                'type' => 'select',
                'label' => Craft::t('commerce', 'Group By'),
                'handle' => 'groupBy',
                'options' => [
                    ['value' => 'day', 'label' => Craft::t('commerce', 'Day')],
                    ['value' => 'week', 'label' => Craft::t('commerce', 'Week')],
                    ['value' => 'month', 'label' => Craft::t('commerce', 'Month')],
                    ['value' => 'year', 'label' => Craft::t('commerce', 'Year')]
                ],
                'default' => 'auto'
            ],
            [
                'type' => 'number',
                'label' => Craft::t('commerce', 'Minimum Orders'),
                'handle' => 'minOrders',
                'default' => 0
            ]
        ];
    }
    
    /**
     * @inheritDoc
     */
    public function getQuery(): Query
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();
        $params = $this->getParamValues();
        
        // Determine the grouping (day, week, month, year)
        $groupBy = $params['groupBy'] ?? 'auto';
        
        // If grouping is set to auto, determine based on date range
        if ($groupBy === 'auto') {
            $dateInterval = $startDate->diff($endDate);
            
            if ($dateInterval->days <= 31) {
                $groupBy = 'day';
            } elseif ($dateInterval->days <= 90) {
                $groupBy = 'week';
            } elseif ($dateInterval->days <= 365) {
                $groupBy = 'month';
            } else {
                $groupBy = 'year';
            }
        }
        
        // Set date format based on grouping
        switch ($groupBy) {
            case 'day':
                $dateFormat = 'DATE_FORMAT(o.dateOrdered, \'%Y-%m-%d\')';
                break;
            case 'week':
                $dateFormat = 'DATE_FORMAT(o.dateOrdered, \'%x-W%v\')'; // ISO year and week
                break;
            case 'month':
                $dateFormat = 'DATE_FORMAT(o.dateOrdered, \'%Y-%m\')';
                break;
            case 'year':
                $dateFormat = 'DATE_FORMAT(o.dateOrdered, \'%Y\')';
                break;
            default:
                $dateFormat = 'DATE_FORMAT(o.dateOrdered, \'%Y-%m-%d\')';
        }
        
        $query = (new Query())
            ->select([
                'date' => $dateFormat,
                'totalRevenue' => 'SUM(o.totalPaid)',
                'orderCount' => 'COUNT(o.id)',
                'averageOrderValue' => 'ROUND(SUM(o.totalPaid) / COUNT(o.id), 2)'
            ])
            ->from(['o' => Table::ORDERS])
            ->where([
                'o.isCompleted' => true,
            ])
            ->andWhere(['between', 'o.dateOrdered', 
                $startDate ? Db::prepareDateForDb($startDate) : Db::prepareDateForDb(new \DateTime('-30 days')), 
                $endDate ? Db::prepareDateForDb($endDate) : Db::prepareDateForDb(new \DateTime())
            ])
            ->groupBy(['date']);
            
        // Apply minimum orders filter if provided
        if (isset($params['minOrders']) && $params['minOrders'] > 0) {
            $query->having(['>=', 'COUNT(o.id)', $params['minOrders']]);
        }
        
        return $query->orderBy(['date' => SORT_ASC]);
    }
}
