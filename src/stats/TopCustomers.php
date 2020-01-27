<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use craft\commerce\base\Stat;
use craft\commerce\Plugin;
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
    protected $_handle = 'topCustomers';

    /**
     * @var string Type of start either 'total' or 'average'.
     */
    public $type = 'total';

    /**
     * @var int Number of customers to show.
     */
    public $limit = 5;

    /**
     * @inheritDoc
     */
    public function __construct(string $dateRange = null, $type = null, $startDate = null, $endDate = null)
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
                new Expression('SUM([[total]]) as total'),
                new Expression('ROUND((SUM([[total]]) / COUNT([[id]])), 4) as average'),
                'customerId',
                '[[orders.email]] as email',
                new Expression('COUNT([[id]]) as count'),
            ])
            ->groupBy(['[[orders.customerId]]', '[[orders.email]]'])
            ->limit($this->limit);

        if ($this->type == 'average') {
            $topCustomers->orderBy(new Expression('ROUND((SUM([[total]]) / COUNT([[id]])), 4) DESC'));
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
            $customer = Plugin::getInstance()->getCustomers()->getCustomerById((int)$topCustomer['customerId']);
            $topCustomer['customer'] = $customer;

            if ($customer && $user = $customer->getUser()) {
                $topCustomer['email'] = $user->email;
            }
        }

        return $data;
    }
}