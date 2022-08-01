<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\cart;

use Craft;
use craft\commerce\base\CartForm;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\errors\ElementNotFoundException;
use Throwable;
use yii\base\InvalidConfigException;

/**
 * Update Coupon Code Form
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2
 */
class UpdateCouponCodeForm extends CartForm
{
    public ?string $couponCode = null;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = ['couponCode', 'trim'];
        $rules[] = ['couponCode', 'required'];
        $rules[] = ['couponCode', 'validateCouponCode'];

        return $rules;
    }

    /**
     * @param string $attribute
     * @return void
     * @throws ElementNotFoundException
     * @throws InvalidConfigException
     * @throws Throwable
     * @throws \yii\base\Exception
     */
    public function validateCouponCode(string $attribute): void
    {
        $recalculateAll = $this->getOrder()->recalculationMode == Order::RECALCULATION_MODE_ALL;
        $recalculateAll = $recalculateAll || $this->getOrder()->recalculationMode == Order::RECALCULATION_MODE_ADJUSTMENTS_ONLY;

        // TODO refactor when updating `orderCouponAvailable` method
        $orderClone = clone $this->getOrder();
        $orderClone->couponCode = $this->couponCode;

        if ($recalculateAll && $this->$attribute && !Plugin::getInstance()->getDiscounts()->orderCouponAvailable($orderClone, $explanation)) {
            $this->addError($attribute, Craft::t('commerce', 'Unable to add coupon: {explanation}', [
                'explanation' => $explanation,
            ]));
        }
    }


    /**
     * @return bool
     * @throws ElementNotFoundException
     * @throws InvalidConfigException
     * @throws Throwable
     * @throws \yii\base\Exception
     */
    public function apply(): bool
    {
        if (!parent::apply()) {
            return false;
        }

        $this->getOrder()->couponCode = $this->couponCode;

        return true;
    }
}
