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
use craft\helpers\ArrayHelper;
use yii\db\Expression;

/**
 * Total Orders by Country Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class TotalOrdersByCountry extends Stat
{
    /**
     * @inheritdoc
     */
    protected $_handle = 'totalOrdersByCountry';

    /**
     * @var string Type of stat e.g. 'shipping' or 'billing'.
     */
    public $type = 'shipping';

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
        $query = $this->_createStatQuery();
        $query->select([
            new Expression('COUNT([[orders.id]]) as total'),
            ($this->type == 'billing' ? '[[bc.id]]' : '[[sc.id]]') . ' as id',
            ($this->type == 'billing' ? '[[bc.name]]' : '[[sc.name]]' ) . ' as name',
        ]);
        $query->leftJoin(Table::ADDRESSES . ' s', '[[s.id]] = [[orders.shippingAddressId]]');
        $query->leftJoin(Table::ADDRESSES . ' b', '[[b.id]] = [[orders.billingAddressId]]');
        $query->leftJoin(Table::COUNTRIES . ' sc', '[[sc.id]] = [[s.countryId]]');
        $query->leftJoin(Table::COUNTRIES . ' bc', '[[bc.id]] = [[b.countryId]]');

        if ($this->type == 'billing') {
            $query->andWhere(['not', ['[[bc.id]]' => null]]);
            $query->groupBy('[[bc.id]]');
        } else {
            $query->andWhere(['not', ['[[sc.id]]' => null]]);
            $query->groupBy('[[sc.id]]');
        }

        $query->orderBy(new Expression('COUNT([[orders.id]]) DESC'));
        $query->limit($this->limit);
        $rows = $query->all();

        if (count($rows) < $this->limit) {
            return $rows;
        }

        $countryIds = ArrayHelper::getColumn($rows, 'id', false);

        $otherCountries = $this->_createStatQuery()
            ->select([
                new Expression('COUNT([[orders.id]]) as total'),
                new Expression('NULL as id'),
            ])
            ->leftJoin(Table::ADDRESSES . ' s', '[[s.id]] = [[orders.shippingAddressId]]')
            ->leftJoin(Table::ADDRESSES . ' b', '[[b.id]] = [[orders.billingAddressId]]')
            ->leftJoin(Table::COUNTRIES . ' sc', '[[sc.id]] = [[s.countryId]]')
            ->leftJoin(Table::COUNTRIES . ' bc', '[[bc.id]] = [[b.countryId]]')
            ->andWhere(['not', [($this->type == 'billing' ? '[[bc.id]]' : '[[sc.id]]') => $countryIds]])
            ->one();

        if (!$otherCountries || empty($otherCountries)) {
            return $rows;
        }

        $otherCountries['name'] = Plugin::t('Other countries');
        $rows[] = $otherCountries;

        return $rows;
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
        if (!empty($data)) {
            foreach ($data as &$row) {
                $row['country'] = null;

                if ($row['id']) {
                    $row['country'] = Plugin::getInstance()->getCountries()->getCountryById($row['id']);
                }
            }
        }

        return $data;
    }
}