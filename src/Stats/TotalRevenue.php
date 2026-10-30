<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use Tpetry\QueryExpressions\Function\Aggregate\Count;
use Tpetry\QueryExpressions\Function\Aggregate\Sum;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * Total Revenue Stat
 */
class TotalRevenue extends Stat
{
    public const TYPE_TOTAL = 'total';

    public const TYPE_TOTAL_PAID = 'totalPaid';

    public string $type = self::TYPE_TOTAL;

    protected string $_handle = 'totalRevenue';

    #[\Override]
    public function getData(): ?array
    {
        $allowedTypes = [self::TYPE_TOTAL, self::TYPE_TOTAL_PAID];
        if (!in_array($this->type, $allowedTypes, true)) {
            $this->type = self::TYPE_TOTAL;
        }

        return $this->createChartQuery(
            [
                new Alias(new Sum($this->type), 'revenue'),
                new Alias(new Count('orders.id'), 'count'),
            ],
            [
                'revenue' => 0,
                'count' => 0,
            ],
        );
    }
}
