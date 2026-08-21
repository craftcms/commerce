<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

/**
 * Repeat Customers Stat
 */
class RepeatCustomers extends Stat
{
    protected string $_handle = 'repeatingCustomers';

    #[\Override]
    public function getData(): array
    {
        $total = $this->createStatQuery()
            ->select('customerId')
            ->groupBy('customerId')
            ->getCountForPagination();

        $repeatRows = $this->createStatQuery()
            ->selectRaw('COUNT(orders.id) as cnt')
            ->groupBy('customerId')
            ->pluck('cnt');

        $repeat = $repeatRows->filter(fn($row) => $row > 1)->count();

        $percentage = round($total ? ($repeat / $total) * 100 : 0);

        return compact('total', 'repeat', 'percentage');
    }
}
