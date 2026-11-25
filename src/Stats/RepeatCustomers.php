<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use Tpetry\QueryExpressions\Function\Aggregate\Count;
use Tpetry\QueryExpressions\Language\Alias;

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
            ->select(new Alias(new Count('orders.id'), 'cnt'))
            ->groupBy('customerId')
            ->pluck('cnt');

        $repeat = $repeatRows->filter(fn($row) => $row > 1)->count();

        $percentage = round($total ? ($repeat / $total) * 100 : 0);

        return compact('total', 'repeat', 'percentage');
    }
}
