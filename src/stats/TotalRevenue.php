<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use craft\commerce\base\Stat;
use yii\db\Expression;

/**
 * Total Revenue Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TotalRevenue extends Stat
{
    public const TYPE_TOTAL = 'total';
    public const TYPE_TOTAL_PAID = 'totalPaid';

    /**
     * @var string
     * @since 4.1
     */
    public string $type = self::TYPE_TOTAL;

    /**
     * @inheritdoc
     */
    protected string $_handle = 'totalRevenue';

    /**
     * @inheritDoc
     */
    public function getData(): ?array
    {
        return $this->_createChartQuery(
            [
                new Expression(sprintf('SUM([[%s]]) as revenue', $this->type)),
                new Expression('COUNT([[orders.id]]) as count'),
            ],
            [
                'revenue' => 0,
                'count' => 0,
            ]
        );
    }
}
