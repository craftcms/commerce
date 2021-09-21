<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use Craft;
use craft\commerce\base\Stat;
use yii\db\Expression;

/**
 * Top Customers Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TopCustomers extends Stat
{
    /**
     * @inheritdoc
     */
    protected string $_handle = 'topCustomers';

    /**
     * @var string Type of start either 'total' or 'average'.
     */
    public string $type = 'total';

    /**
     * @var int Number of customers to show.
     */
    public int $limit = 5;

    /**
     * @inheritDoc
     */
    public function __construct(string $dateRange = null, string $type = null, $startDate = null, $endDate = null)
    {
        if ($type) {
            $this->type = $type;
        }

        parent::__construct($dateRange, $startDate, $endDate);
    }

    /**
     * @inheritDoc
     */
    public function getData()
    {
        $topCustomers = $this->_createStatQuery()
            ->select([
                'average' => new Expression('ROUND((SUM([[total]]) / COUNT([[orders.id]])), 4)'),
                'count' => new Expression('COUNT([[orders.id]])'),
                'userId',
                'email' => '[[orders.email]]',
                'total' => new Expression('SUM([[total]])'),
            ])
            ->groupBy(['[[orders.userId]]', '[[orders.email]]'])
            ->limit($this->limit);

        if ($this->type == 'average') {
            $topCustomers->orderBy(new Expression('ROUND((SUM([[total]]) / COUNT([[orders.id]])), 4) DESC'));
        } else {
            $topCustomers->orderBy(new Expression('SUM([[total]]) DESC'));
        }

        return $topCustomers->all();
    }

    /**
     * @inheritDoc
     */
    public function getHandle(): string
    {
        return $this->_handle . $this->type;
    }

    /**
     * @inheritDoc
     */
    public function prepareData($data)
    {
        foreach ($data as &$topCustomer) {
            $topCustomer['user'] = Craft::$app->getUsers()->getUserById($topCustomer['userId']);

            if ($topCustomer['user']) {
                $topCustomer['email'] = $topCustomer['user']->email;
            }
        }

        return $data;
    }
}