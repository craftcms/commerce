<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\validators;

use Craft;
use craft\commerce\records\Coupon;
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
        $query = Coupon::find()
            ->where(['in', 'code', $codes]);

        if ($model->id) {
            $query->andWhere(['not', ['discountId' => $model->id]]);
        }

        $codeCount = $query->count();

        if ($codeCount) {
            $this->addError($model, $attribute, Craft::t('commerce', 'Coupon codes must be unique.'));
        }
    }
}