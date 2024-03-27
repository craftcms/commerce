<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use Craft;
use craft\commerce\base\Stat;
use craft\db\Table as CraftTable;
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
    protected string $_handle = 'totalOrdersByCountry';

    /**
     * @var string Type of stat e.g. 'shipping' or 'billing'.
     */
    public string $type = 'shipping';

    public int $limit = 5;

    /**
     * @inheritDoc
     */
    public function __construct(string $dateRange = null, string $type = null, $startDate = null, $endDate = null, ?int $storeId = null)
    {
        $this->type = $type ?? $this->type;

        parent::__construct($dateRange, $startDate, $endDate, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        $query = $this->_createStatQuery();
        $query->select([
            'countryCode' => ($this->type == 'billing' ? '[[b.countryCode]]' : '[[s.countryCode]]'),
            'total' => new Expression('COUNT([[orders.id]])'),
        ]);
        $query->leftJoin(CraftTable::ADDRESSES . ' s', '[[s.id]] = [[orders.shippingAddressId]]');
        $query->leftJoin(CraftTable::ADDRESSES . ' b', '[[b.id]] = [[orders.billingAddressId]]');

        if ($this->type == 'billing') {
            $query->andWhere(['not', ['[[b.countryCode]]' => null]]);
            $query->groupBy('[[b.countryCode]]');
        } else {
            $query->andWhere(['not', ['[[s.countryCode]]' => null]]);
            $query->groupBy('[[s.countryCode]]');
        }

        $query->orderBy(new Expression('COUNT([[orders.id]]) DESC'));
        $query->limit($this->limit);
        $rows = $query->all();

        if (count($rows) < $this->limit) {
            return $rows;
        }

        $countryCodes = ArrayHelper::getColumn($rows, 'countryCode', false);

        $otherCountries = $this->_createStatQuery()
            ->select([
                'total' => new Expression('COUNT([[orders.id]])'),
                'countryCode' => new Expression('NULL'),
            ])
            ->leftJoin(CraftTable::ADDRESSES . ' s', '[[s.id]] = [[orders.shippingAddressId]]')
            ->leftJoin(CraftTable::ADDRESSES . ' b', '[[b.id]] = [[orders.billingAddressId]]')
            ->andWhere(['not', [($this->type == 'billing' ? '[[b.countryCode]]' : '[[s.countryCode]]') => $countryCodes]])
            ->one();

        if (empty($otherCountries)) {
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
    public function prepareData($data): mixed
    {
        if (!empty($data)) {
            foreach ($data as &$row) {
                if (!$row['countryCode']) {
                    continue;
                }
                $row['name'] = Craft::$app->getAddresses()->getCountryRepository()->get($row['countryCode'])->getName();
            }
        }

        return $data;
    }
}
