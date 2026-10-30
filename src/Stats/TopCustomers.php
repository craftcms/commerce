<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use CraftCms\Cms\Database\Table as CmsTable;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Commerce\Support\Expressions\Round;
use Tpetry\QueryExpressions\Function\Aggregate\Count;
use Tpetry\QueryExpressions\Function\Aggregate\Sum;
use Tpetry\QueryExpressions\Language\Alias;
use Tpetry\QueryExpressions\Operator\Arithmetic\Divide;

/**
 * Top Customers Stat
 */
class TopCustomers extends Stat
{
    protected string $_handle = 'topCustomers';

    /**
     * Type of start either 'total' or 'average'.
     */
    public string $type = 'total';

    /**
     * Number of customers to show.
     */
    public int $limit = 5;

    public function __construct(?string $dateRange = null, ?string $type = null, $startDate = null, $endDate = null, ?int $storeId = null)
    {
        if ($type) {
            $this->type = $type;
        }

        parent::__construct($dateRange, $startDate, $endDate, $storeId);
    }

    #[\Override]
    public function getData(): array
    {
        $averageExpression = new Round(new Divide(new Sum('total'), new Count('orders.id')), 4);

        $topCustomers = $this->createStatQuery()
            ->select([
                new Alias($averageExpression, 'average'),
                new Alias(new Count('orders.id'), 'count'),
                'customerId',
                new Alias(new Sum('total'), 'total'),
                'users.email',
            ])
            ->join(CmsTable::USERS . ' as users', 'orders.customerId', '=', 'users.id')
            ->groupBy(['orders.customerId', 'users.email'])
            ->limit($this->limit);

        if ($this->type == 'average') {
            $topCustomers->orderBy($averageExpression, 'desc');
        } else {
            $topCustomers->orderBy(new Sum('total'), 'desc');
        }

        return $topCustomers->get()->map(fn($row) => (array)$row)->all();
    }

    #[\Override]
    public function getHandle(): string
    {
        return $this->_handle . $this->type;
    }

    #[\Override]
    public function prepareData($data): mixed
    {
        foreach ($data as &$topCustomer) {
            $topCustomer['customer'] = Users::getUserById($topCustomer['customerId']);
        }

        return $data;
    }
}
