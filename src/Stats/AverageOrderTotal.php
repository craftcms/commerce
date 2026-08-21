<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Stats;

use CraftCms\Commerce\Support\Expressions\Round;
use Tpetry\QueryExpressions\Function\Aggregate\Count;
use Tpetry\QueryExpressions\Function\Aggregate\Sum;
use Tpetry\QueryExpressions\Language\Alias;
use Tpetry\QueryExpressions\Operator\Arithmetic\Divide;

/**
 * Average Order Total Stat
 */
class AverageOrderTotal extends Stat
{
    protected string $_handle = 'averageOrderTotal';

    #[\Override]
    public function getData(): string|int|float|bool|null
    {
        return $this->createStatQuery()
            ->select(new Alias(new Round(new Divide(new Sum('total'), new Count('orders.id')), 4), 'averageOrderTotal'))
            ->value('averageOrderTotal');
    }
}
