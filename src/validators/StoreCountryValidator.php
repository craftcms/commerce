<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\validators;

use Craft;
use craft\commerce\Plugin;
use yii\validators\Validator;

/**
 * Class StoreCountryValidator.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class StoreCountryValidator extends Validator
{
    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute): void
    {
        $countriesList = array_keys(Plugin::getInstance()->getStore()->getStore()->getCountriesList());
        if (!in_array($model->$attribute, $countriesList, false)) {
            $this->addError($model, $attribute, Craft::t('commerce', 'Country not allowed.'));
        }
    }
}
