<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use CraftCms\Commerce\Database\Table;
use Illuminate\Support\Facades\DB;
use Tpetry\QueryExpressions\Function\Aggregate\Count;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * New Customers Stat
 */
class NewCustomers extends Stat
{
    protected string $_handle = 'newCustomers';

    #[\Override]
    public function getData(): string|int|bool|null
    {
        $query = $this->createStatQuery();

        // Subquery to find customers who have orders before the start date
        $existingCustomersQuery = DB::table(Table::ORDERS)
            ->select('customerId')
            ->where('isCompleted', true)
            ->whereNotNull('customerId')
            ->where('dateOrdered', '<', $this->formatDateForDb($this->getStartDate()));

        return $query->select(new Alias(new Count('customerId', distinct: true), 'newCustomers'))
            ->whereNotNull('customerId')
            ->whereNotIn('customerId', $existingCustomersQuery)
            ->value('newCustomers');
    }
}
