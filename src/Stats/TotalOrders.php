<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use Illuminate\Support\Facades\DB;

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
            DB::raw('COUNT(orders.id) as total'),
        ], [
            'total' => 0,
        ]);

        return [
            'total' => $total,
            'chart' => $chartData,
        ];
    }
}
