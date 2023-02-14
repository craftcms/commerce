<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Zone;
use craft\commerce\records\ShippingZone;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;
use yii\base\InvalidConfigException;

/**
 * Shipping zone model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property-read string $cpEditUrl
 */
class ShippingAddressZone extends Zone
{
    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['name'], UniqueValidator::class, 'targetClass' => ShippingZone::class, 'targetAttribute' => ['name', 'storeId']];

        return $rules;
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/store-settings/' . $this->getStore()->handle . '/shippingzones/' . $this->id);
    }
}
