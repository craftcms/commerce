<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Zone;
use craft\commerce\records\ShippingZone as ShippingZoneRecord;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;

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
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/shipping/shippingzones/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['name'], 'required'],
            [['condition'], 'required'],
            [['name'], UniqueValidator::class, 'targetClass' => ShippingZoneRecord::class, 'targetAttribute' => ['name']],
        ];
    }
}
