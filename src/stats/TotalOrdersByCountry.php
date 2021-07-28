<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use Craft;
use craft\commerce\base\Stat;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use DateTime;
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
    protected string $_handle = 'totalOrdersByCountry';

    /**
     * @var string Type of stat e.g. 'shipping' or 'billing'.
     */
    public string $type = 'shipping';

    public int $limit = 5;

    /**
     * @inheritDoc
     */
    public function __construct(string $dateRange = null, string $type = null, $startDate = null, $endDate = null)
    {
        $this->type = $type ?? $this->type;

        parent::__construct($dateRange, $startDate, $endDate);
    }

    /**
     * @inheritDoc
     */
    public function getData()
    {
        $query = $this->_createStatQuery();
        $query->select([
            'id' => ($this->type == 'billing' ? '[[bc.id]]' : '[[sc.id]]'),
            'name' => ($this->type == 'billing' ? '[[bc.name]]' : '[[sc.name]]'),
            'total' => new Expression('COUNT([[orders.id]])'),
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
                'total' => new Expression('COUNT([[orders.id]])'),
                'id' => new Expression('NULL'),
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

        $otherCountries['name'] = Craft::t('commerce', 'Other countries');
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
