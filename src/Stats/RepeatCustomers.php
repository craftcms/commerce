<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use Tpetry\QueryExpressions\Function\Aggregate\Count;

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

        $repeat = $this->createStatQuery()
            ->select('customerId')
            ->groupBy('customerId')
            ->having(new Count('orders.id'), '>', 1)
            ->getCountForPagination();

        $percentage = round($total ? ($repeat / $total) * 100 : 0);

        return compact('total', 'repeat', 'percentage');
    }
}
