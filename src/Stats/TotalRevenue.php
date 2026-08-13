<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use Illuminate\Support\Facades\DB;

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
                DB::raw(sprintf('SUM(%s) as revenue', $this->type)),
                DB::raw('COUNT(orders.id) as count'),
            ],
            [
                'revenue' => 0,
                'count' => 0,
            ],
        );
    }
}
