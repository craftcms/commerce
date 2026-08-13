<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

/**
 * Average Order Total Stat
 */
class AverageOrderTotal extends Stat
{
    protected string $_handle = 'averageOrderTotal';

    #[\Override]
    public function getData(): string|int|bool|null
    {
        return $this->createStatQuery()
            ->selectRaw('ROUND(SUM(total) / COUNT(orders.id), 4) as averageOrderTotal')
            ->value('averageOrderTotal');
    }
}
