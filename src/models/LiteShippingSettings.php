<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;

/**
 * Class Lite Shipping Settings
 *
 * @property-read string $taxRateAsPercent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @deprecated in 4.5.0
 *
 */
class LiteShippingSettings extends Model
{
    /**
     * @var float Shipping base rate
     */
    public float $shippingBaseRate;

    /**
     * @var float Shipping per item rate
     */
    public float $shippingPerItemRate;

    /**
     * @return array
     */
    public function safeAttributes(): array
    {
        return [
            'shippingBaseRate',
            'shippingPerItemRate',
        ];
    }

    /**
     * @return array[]
     */
    protected function defineRules(): array
    {
        return [[['shippingBaseRate', 'shippingPerItemRate'], 'required']];
    }
}
