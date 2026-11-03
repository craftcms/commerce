<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Management\Access;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use InvalidArgumentException;

readonly class Reports
{
    public const array TYPES = [
        'totalOrders' => TotalOrders::class,
        'totalRevenue' => TotalRevenue::class,
        'averageOrderTotal' => AverageOrderTotal::class,
        'topProducts' => TopProducts::class,
        'topProductTypes' => TopProductTypes::class,
        'topPurchasables' => TopPurchasables::class,
        'totalOrdersByCountry' => TotalOrdersByCountry::class,
        'topCustomers' => TopCustomers::class,
        'newCustomers' => NewCustomers::class,
        'repeatCustomers' => RepeatCustomers::class,
    ];

    public function __construct(private Access $access)
    {
    }

    public function get(CraftUser $actor, int $storeId, string $type, string $dateRange = 'past30Days', int $limit = 20): array
    {
        $this->access->permission($actor, 'commerce-manageOrders');
        $this->access->store($actor, $storeId);
        if (!isset(self::TYPES[$type]) || !isset(StatInterface::DATE_RANGE_INTERVAL[$dateRange]) || $limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Choose a supported report and date range, and a limit between 1 and 100.');
        }
        if (in_array($type, ['topCustomers', 'newCustomers', 'repeatCustomers'], true)) {
            $this->access->permission($actor, 'commerce-manageCustomers');
        }
        $class = self::TYPES[$type];
        $stat = new $class(dateRange: $dateRange, storeId: $storeId);
        if (property_exists($stat, 'limit')) {
            $stat->limit = $limit;
        }
        // getData() returns report values, without prepareData()'s hydrated customer elements.
        return ['type' => $type, 'storeId' => $storeId, 'dateRange' => $dateRange, 'data' => $stat->getData()];
    }
}
