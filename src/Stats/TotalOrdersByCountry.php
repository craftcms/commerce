<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use CraftCms\Cms\Database\Table as CmsTable;

use function CraftCms\Cms\t;

/**
 * Total Orders by Country Stat
 */
class TotalOrdersByCountry extends Stat
{
    protected string $_handle = 'totalOrdersByCountry';

    /**
     * Type of stat e.g. 'shipping' or 'billing'.
     */
    public string $type = 'shipping';

    public int $limit = 5;

    public function __construct(?string $dateRange = null, ?string $type = null, $startDate = null, $endDate = null, ?int $storeId = null)
    {
        $this->type = $type ?? $this->type;

        parent::__construct($dateRange, $startDate, $endDate, $storeId);
    }

    #[\Override]
    public function getData(): array
    {
        $countryColumn = $this->type == 'billing' ? 'b.countryCode' : 's.countryCode';

        $query = $this->createStatQuery()
            ->select(["$countryColumn as countryCode"])
            ->selectRaw('COUNT(orders.id) as total')
            ->leftJoin(CmsTable::ADDRESSES . ' as s', 's.id', '=', 'orders.shippingAddressId')
            ->leftJoin(CmsTable::ADDRESSES . ' as b', 'b.id', '=', 'orders.billingAddressId')
            ->whereNotNull($countryColumn)
            ->groupBy($countryColumn)
            ->orderByRaw('COUNT(orders.id) DESC')
            ->limit($this->limit);

        $rows = $query->get()->map(fn($row) => (array)$row)->all();

        if (count($rows) < $this->limit) {
            return $rows;
        }

        $countryCodes = array_column($rows, 'countryCode');

        $otherCountries = $this->createStatQuery()
            ->selectRaw('COUNT(orders.id) as total')
            ->selectRaw('NULL as countryCode')
            ->leftJoin(CmsTable::ADDRESSES . ' as s', 's.id', '=', 'orders.shippingAddressId')
            ->leftJoin(CmsTable::ADDRESSES . ' as b', 'b.id', '=', 'orders.billingAddressId')
            ->whereNotIn($countryColumn, $countryCodes)
            ->first();

        $otherCountries = $otherCountries ? (array)$otherCountries : [];

        if (empty($otherCountries)) {
            return $rows;
        }

        $otherCountries['name'] = t('Other countries', category: 'commerce');
        $rows[] = $otherCountries;

        return $rows;
    }

    #[\Override]
    public function getHandle(): string
    {
        return $this->_handle . $this->type;
    }

    #[\Override]
    public function prepareData($data): mixed
    {
        if (!empty($data)) {
            foreach ($data as &$row) {
                if (!$row['countryCode']) {
                    continue;
                }
                $row['name'] = \Craft::$app->getAddresses()->getCountryRepository()->get($row['countryCode'])->getName();
            }
        }

        return $data;
    }
}
