<?php

namespace craft\commerce\reports;

use Craft;
use craft\commerce\base\Report;
use craft\commerce\db\Table;
use craft\db\Query;
use craft\helpers\Db;

class SalesBySku extends Report
{
    /**
     * @inheritDoc
     */
    public function getHandle(): ?string
    {
        return 'salesBySku';
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return Craft::t('commerce', 'Sales By SKU');
    }

    /**
     * @inheritDoc
     */
    public function getColumns(): array
    {
        return [
            [
                'label' => Craft::t('commerce', 'SKU'),
                'value' => 'sku',
                'type' => 'string',
            ],
            [
                'label' => Craft::t('commerce', 'Description'),
                'value' => 'description',
                'type' => 'string',
            ],
            [
                'label' => Craft::t('commerce', 'Quantity Sold'),
                'value' => 'quantitySold',
                'type' => 'number',
            ],
            [
                'label' => Craft::t('commerce', 'Total Revenue'),
                'value' => 'totalRevenue',
                'type' => 'money',
            ],
            [
                'label' => Craft::t('commerce', 'Average Price'),
                'value' => 'averagePrice',
                'type' => 'money',
            ],
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
                'label' => Craft::t('commerce', 'Sort By'),
                'handle' => 'sortBy',
                'options' => [
                    ['value' => 'quantitySold', 'label' => Craft::t('commerce', 'Quantity Sold')],
                    ['value' => 'totalRevenue', 'label' => Craft::t('commerce', 'Total Revenue')],
                    ['value' => 'averagePrice', 'label' => Craft::t('commerce', 'Average Price')],
                    ['value' => 'sku', 'label' => Craft::t('commerce', 'SKU')],
                ],
                'default' => 'quantitySold',
            ],
            [
                'type' => 'select',
                'label' => Craft::t('commerce', 'Sort Direction'),
                'handle' => 'sortDir',
                'options' => [
                    ['value' => 'desc', 'label' => Craft::t('commerce', 'Descending')],
                    ['value' => 'asc', 'label' => Craft::t('commerce', 'Ascending')],
                ],
                'default' => 'desc',
            ],
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
        
        $query = (new Query())
            ->select([
                'sku' => 'li.sku',
                'description' => 'ANY_VALUE(li.description)', // Use ANY_VALUE to satisfy ONLY_FULL_GROUP_BY
                'quantitySold' => 'SUM(li.qty)',
                'totalRevenue' => 'SUM(li.total)',
                'averagePrice' => 'ROUND(SUM(li.total) / SUM(li.qty), 2)',
            ])
            ->from(['li' => Table::LINEITEMS])
            ->innerJoin(['o' => Table::ORDERS], '[[o.id]] = [[li.orderId]]')
            ->where([
                'o.isCompleted' => true,
            ])
            ->andWhere(['between', 'o.dateOrdered',
                $startDate ? Db::prepareDateForDb($startDate) : Db::prepareDateForDb(new \DateTime('-30 days')),
                $endDate ? Db::prepareDateForDb($endDate) : Db::prepareDateForDb(new \DateTime()),
            ])
            ->groupBy(['li.sku']);
            
        // No SKU filter
        
        // Apply sorting
        $sortBy = $params['sortBy'] ?? 'quantitySold';
        $sortDir = ($params['sortDir'] ?? 'desc') === 'desc' ? SORT_DESC : SORT_ASC;
        
        return $query->orderBy([$sortBy => $sortDir]);
    }
}
