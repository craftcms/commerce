<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use craft\commerce\models\Coupon;
use Craft;
use craft\commerce\db\Table;
use craft\commerce\records\Coupon as CouponRecord;
use craft\db\Query;
use Exception;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Coupons service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 *
 * @property-read array $allCoupons
 */
class Coupons extends Component
{
    /**
     * @var array|null
     */
    private ?array $_allCoupons = null;

    /**
     * @return array
     */
    public function getAllCoupons(): array
    {
        if ($this->_allCoupons !== null) {
            return $this->_allCoupons;
        }

        $this->_allCoupons = $this->_createCouponQuery()->all();

        return $this->_allCoupons;
    }

    /**
     * @param string $code
     * @return Coupon|null
     * @throws InvalidConfigException
     */
    public function getCouponByCode(string $code): ?Coupon
    {
        $coupon = $this->_createCouponQuery()
            ->where(['code' => $code])
            ->one();

        return $coupon ? Craft::createObject(Coupon::class, ['config' => ['attributes' => $coupon]]) : null;
    }

    /**
     * @param string $format
     * @return string[]
     */
    public function generateCouponCodes(string $format, int $count = 10): array
    {
        if (!$format) {
            throw new \InvalidArgumentException(Craft::t('commerce', 'Coupon code format cannot be empty.'));
        }

        $codes = [];

        try {
            $render = '{% for i in 1..'.$count.' -%}' . $format . '\n{%- endfor %}';
            $output = Craft::$app->getView()->renderString($render, []);
            $codes = array_filter(explode('\n', $output));
        } catch(Exception $exception) {
            Craft::info($exception->getMessage(), __METHOD__);
        }

        return $codes;
    }

    /**
     * @param int $id
     * @return Coupon|null
     * @throws InvalidConfigException
     */
    // public function getCouponById(int $id): ?Coupon
    // {
    //     $coupon = $this->_createCouponQuery()
    //         ->where(['id' => $id])
    //         ->one();
    //
    //     return $coupon ? Craft::createObject(Coupon::class, ['config' => ['attributes' => $coupon]]) : null;
    // }

    /**
     * @param Coupon $coupon
     * @param bool $runValidation
     * @return bool
     * @throws Exception
     */
    public function saveCoupon(Coupon $coupon, bool $runValidation = true): bool
    {
        if ($coupon->id) {
            $record = CouponRecord::findOne($coupon->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No coupon exists with the ID “{id}”', ['id' => $coupon->id]));
            }
        } else {
            $record = new CouponRecord();
        }

        if ($runValidation && !$coupon->validate()) {
            Craft::info('Coupon not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->code = $coupon->code;

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $coupon->id = $record->id;

        $this->clearCaches();

        return true;
    }

    protected function clearCaches(): void
    {
        $this->_allCoupons = null;
    }

    /**
     * Returns a Query object prepped for retrieving Coupons.
     *
     * @return Query The query object.
     */
    private function _createCouponQuery(): Query
    {
        return (new Query())
            ->select([
                'coupons.dateCreated',
                'coupons.dateUpdated',
                'coupons.id',
                'coupons.code',
            ])
            ->from([Table::COUPONS . ' coupons']);
    }
}
