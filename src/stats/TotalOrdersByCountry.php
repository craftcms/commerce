<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use craft\commerce\base\Stat;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use yii\db\Expression;

/**
 * Total Orders by Country Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TotalOrdersByCountry extends Stat
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected $_handle = 'totalOrdersByCountry';

    /**
     * @var string Type of stat e.g. 'shipping' or 'billing'.
     */
    public $type = 'shipping';

    /**
     * @var int
     */
    public $limit = 5;

    // Public Methods
    // =========================================================================

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
        $query = $this->_createStatQuery();
        $query->select([
            new Expression('COUNT([[orders.id]]) as totalOrders'),
            '[[sc.id]] as shippingCountryId',
            '[[bc.id]] as billingCountryId',
        ]);
        $query->leftJoin(Table::ADDRESSES . ' s', '[[s.id]] = [[orders.shippingAddressId]]');
        $query->leftJoin(Table::ADDRESSES . ' b', '[[b.id]] = [[orders.billingAddressId]]');
        $query->leftJoin(Table::COUNTRIES . ' sc', '[[sc.id]] = [[s.countryId]]');
        $query->leftJoin(Table::COUNTRIES . ' bc', '[[bc.id]] = [[b.countryId]]');

        if ($this->type == 'billing') {
            $query->groupBy('[[bc.id]]');
        } else {
            $query->groupBy('[[sc.id]]');
        }

        $query->orderBy(new Expression('COUNT([[orders.id]]) DESC'));
        $query->limit($this->limit);

        return $query->all();
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
    public function processData($data)
    {
        if (!empty($data)) {
            foreach ($data as &$row) {
                $row['billingCountry'] = null;
                $row['shippingCountry'] = null;

                if ($row['billingCountryId']) {
                    $row['billingCountry'] = Plugin::getInstance()->getCountries()->getCountryById($row['billingCountryId']);
                }

                if ($row['shippingCountryId']) {
                    $row['shippingCountry'] = Plugin::getInstance()->getCountries()->getCountryById($row['shippingCountryId']);
                }
            }
        }

        return $data;
    }
}