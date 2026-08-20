<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use CraftCms\Cms\Database\Table as CmsTable;
use CraftCms\Cms\Support\Facades\Addresses;
use Tpetry\QueryExpressions\Function\Aggregate\Count;
use Tpetry\QueryExpressions\Language\Alias;
use Tpetry\QueryExpressions\Value\Value;

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
            ->addSelect(new Alias(new Count('orders.id'), 'total'))
            ->leftJoin(CmsTable::ADDRESSES . ' as s', 's.id', '=', 'orders.shippingAddressId')
            ->leftJoin(CmsTable::ADDRESSES . ' as b', 'b.id', '=', 'orders.billingAddressId')
            ->whereNotNull($countryColumn)
            ->groupBy($countryColumn)
            ->orderBy(new Count('orders.id'), 'desc')
            ->limit($this->limit);

        $rows = $query->get()->map(fn($row) => (array)$row)->all();

        if (count($rows) < $this->limit) {
            return $rows;
        }

        $countryCodes = array_column($rows, 'countryCode');

        $otherCountries = $this->createStatQuery()
            ->select(new Alias(new Count('orders.id'), 'total'))
            ->addSelect(new Alias(new Value(null), 'countryCode'))
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
                $row['name'] = Addresses::getCountryRepository()->get($row['countryCode'])->getName();
            }
        }

        return $data;
    }
}
