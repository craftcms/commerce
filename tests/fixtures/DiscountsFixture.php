<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\models\Coupon;
use craft\commerce\models\Discount;
use craft\commerce\Plugin;
use craft\commerce\records\Coupon as CouponRecord;

/**
 * Class DiscountsFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 2.1
 */
class DiscountsFixture extends BaseModelFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/discounts.php';

    /**
     * @inheritdoc
     */
    public $modelClass = Discount::class;

    /**
     * @inheritDoc
     */
    public string $saveMethod = 'saveDiscount';

    /**
     * @inheritDoc
     */
    public string $deleteMethod = 'deleteDiscountById';

    /**
     * @inheritDoc
     */
    public $service = 'discounts';

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->service = Plugin::getInstance()->get($this->service);

        parent::init();
    }

    /**
     * @inheritdoc
     */
    protected function prepData($data)
    {
        if (empty($data['_coupons'])) {
            unset($data['_coupons']);
            return $data;
        }

        $data['coupons'] = [];
        foreach ($data['_coupons'] as $c) {
            $data['coupons'][] = \Craft::createObject(Coupon::class, ['config' => [
                'attributes' => $c,
            ]]);
        }

        unset($data['_coupons']);
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function unload(): void
    {
        // TODO: Figure out why the cascade delete is not working for coupons in tests
        if (isset($this->data) && !empty($this->data)) {
            foreach ($this->data as $discount) {
                $coupons = CouponRecord::find()->where(['discountId' => $discount['id']])->all();

                if (empty($coupons)) {
                    continue;
                }

                foreach ($coupons as $coupon) {
                    $coupon->delete();
                }
            }
        }

        parent::unload();
    }
}
