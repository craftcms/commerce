<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\validators;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\records\Coupon;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use yii\validators\Validator;

/**
 * Class CouponsValidator.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class CouponsValidator extends Validator
{
    /**
     * @param \craft\commerce\models\Coupon $model the coupon model to be validated
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute): void
    {
        $codes = ArrayHelper::getColumn($model->$attribute, 'code');

        // Make sure there aren't any blank lines
        if (array_filter($codes) !== $codes) {
            $this->addError($model, $attribute, Craft::t('commerce', 'Coupon codes cannot be blank.'));
        }

        // Case-insensitive check for duplicates in the same set of codes
        if (array_intersect_key($codes, array_unique(array_map('strtolower', $codes))) !== $codes) {
            $this->addError($model, $attribute, Craft::t('commerce', 'Coupon codes must be unique.'));
            return;
        }

        // Check other codes in the DB
        $query = (new Query())
            ->select([
                'coupons.code',
                'discounts.name',
            ])
            ->from(Table::COUPONS . ' coupons')
            ->leftJoin(Table::DISCOUNTS . ' discounts', '[[discounts.id]] = [[coupons.discountId]]')
            ->where(['in', 'code', $codes]);

        if ($model->id) {
            $query->andWhere(['not', ['discountId' => $model->id]]);
        }

        $existingDiscounts = $query->all();

        if (count($existingDiscounts)) {
            foreach ($existingDiscounts as $existingDiscount) {
                $this->addError($model, $attribute, Craft::t('commerce', 'Coupon code “{code}” is already in use by discount “{name}”.', [
                    'code' => $existingDiscount['code'],
                    'name' => $existingDiscount['name'],
                ]));
            }
        }
    }
}
