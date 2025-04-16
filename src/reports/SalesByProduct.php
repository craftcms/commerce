<?php

namespace craft\commerce\reports;

use Craft;
use craft\commerce\base\Report;
use craft\commerce\db\Table;
use craft\db\Query;
use craft\helpers\Db;

class SalesByProduct extends Report
{
    /**
     * @inheritDoc
     */
    public function getHandle(): ?string
    {
        return 'salesByProduct';
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return Craft::t('commerce', 'Sales By Product');
    }
    
    /**
     * @inheritDoc
     */
    public function getIcon(): ?string
    {
        return 'package';
    }
    
    /**
     * @inheritDoc
     */
    public function getColumns(): array
    {
        return [
            [
                'label' => Craft::t('commerce', 'Product Title'),
                'value' => 'productTitle',
                'type' => 'string'
            ],
            [
                'label' => Craft::t('commerce', 'Quantity Sold'),
                'value' => 'quantitySold',
                'type' => 'number'
            ],
            [
                'label' => Craft::t('commerce', 'Total Revenue'),
                'value' => 'totalRevenue',
                'type' => 'money'
            ],
            [
                'label' => Craft::t('commerce', 'Average Price'),
                'value' => 'averagePrice',
                'type' => 'money'
            ],
            [
                'label' => Craft::t('commerce', 'Order Count'),
                'value' => 'orderCount',
                'type' => 'number'
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
                'type' => 'text',
                'label' => Craft::t('commerce', 'Product Title Filter'),
                'handle' => 'titleFilter',
                'default' => '',
                'required' => false
            ],
            [
                'type' => 'select',
                'label' => Craft::t('commerce', 'Sort By'),
                'handle' => 'sortBy',
                'options' => [
                    ['value' => 'totalRevenue', 'label' => Craft::t('commerce', 'Total Revenue')],
                    ['value' => 'quantitySold', 'label' => Craft::t('commerce', 'Quantity Sold')],
                    ['value' => 'orderCount', 'label' => Craft::t('commerce', 'Order Count')],
                    ['value' => 'averagePrice', 'label' => Craft::t('commerce', 'Average Price')],
                    ['value' => 'productTitle', 'label' => Craft::t('commerce', 'Product Title')]
                ],
                'default' => 'totalRevenue'
            ],
            [
                'type' => 'select',
                'label' => Craft::t('commerce', 'Sort Direction'),
                'handle' => 'sortDir',
                'options' => [
                    ['value' => 'desc', 'label' => Craft::t('commerce', 'Descending')],
                    ['value' => 'asc', 'label' => Craft::t('commerce', 'Ascending')]
                ],
                'default' => 'desc'
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
        
        $query = (new Query())
            ->select([
                'productTitle' => 'SUBSTRING_INDEX(li.description, " - ", 1)', // Extract just the product title before any variant info
                'quantitySold' => 'SUM(li.qty)',
                'totalRevenue' => 'SUM(li.total)',
                'averagePrice' => 'ROUND(SUM(li.total) / SUM(li.qty), 2)',
                'orderCount' => 'COUNT(DISTINCT o.id)'
            ])
            ->from(['li' => Table::LINEITEMS])
            ->innerJoin(['o' => Table::ORDERS], '[[o.id]] = [[li.orderId]]')
            ->where([
                'o.isCompleted' => true,
            ])
            ->andWhere(['between', 'o.dateOrdered', 
                $startDate ? Db::prepareDateForDb($startDate) : Db::prepareDateForDb(new \DateTime('-30 days')), 
                $endDate ? Db::prepareDateForDb($endDate) : Db::prepareDateForDb(new \DateTime())
            ])
            ->andWhere(['not', ['li.purchasableId' => null]]) // Ensure we're only looking at actual product line items
            ->groupBy(['productTitle']);
            
        // Apply title filter if provided
        if (!empty($params['titleFilter'])) {
            $query->andWhere(['like', 'li.description', $params['titleFilter']]);
        }
        
        // Apply minimum orders filter if provided
        if (isset($params['minOrders']) && $params['minOrders'] > 0) {
            $query->having(['>=', 'COUNT(DISTINCT o.id)', $params['minOrders']]);
        }
        
        // Apply sorting
        $sortBy = $params['sortBy'] ?? 'totalRevenue';
        $sortDir = ($params['sortDir'] ?? 'desc') === 'desc' ? SORT_DESC : SORT_ASC;
        
        return $query->orderBy([$sortBy => $sortDir]);
    }
}
