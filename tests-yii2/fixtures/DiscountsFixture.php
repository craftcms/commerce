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
use CraftCms\Commerce\Promotion\Models\Coupon as CouponRecord;

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
        // @TODO Investigate why the FK cascade delete on coupons does not fire during fixture unload, then remove this manual cleanup
        if (isset($this->data) && !empty($this->data)) {
            foreach ($this->data as $discount) {
                $coupons = CouponRecord::where('discountId', $discount['id'])->get();

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
