<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use Tpetry\QueryExpressions\Function\Aggregate\Count;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * Total Orders Stat
 */
class TotalOrders extends Stat
{
    protected string $_handle = 'totalOrders';

    #[\Override]
    public function getData(): array
    {
        $total = $this->createStatQuery()->count();

        $chartData = $this->createChartQuery([
            new Alias(new Count('orders.id'), 'total'),
        ], [
            'total' => 0,
        ]);

        return [
            'total' => $total,
            'chart' => $chartData,
        ];
    }
}
