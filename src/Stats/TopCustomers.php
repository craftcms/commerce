<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use CraftCms\Cms\Database\Table as CmsTable;

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
        $topCustomers = $this->createStatQuery()
            ->selectRaw('ROUND((SUM(total) / COUNT(orders.id)), 4) as average')
            ->selectRaw('COUNT(orders.id) as count')
            ->addSelect('customerId')
            ->selectRaw('SUM(total) as total')
            ->addSelect('users.email')
            ->join(CmsTable::USERS . ' as users', 'orders.customerId', '=', 'users.id')
            ->groupBy(['orders.customerId', 'users.email'])
            ->limit($this->limit);

        if ($this->type == 'average') {
            $topCustomers->orderByRaw('ROUND((SUM(total) / COUNT(orders.id)), 4) DESC');
        } else {
            $topCustomers->orderByRaw('SUM(total) DESC');
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
            $topCustomer['customer'] = \Craft::$app->getUsers()->getUserById($topCustomer['customerId']);
        }

        return $data;
    }
}
