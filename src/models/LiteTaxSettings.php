<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\helpers\Localization;

/**
 * Class Lite Tax Settings
 *
 * @property-read string $taxRateAsPercent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 */
class LiteTaxSettings extends Model
{
    /**
     * @var float Tax rate
     */
    public float $taxRate;

    /**
     * @var string Tax name
     */
    public string $taxName;

    /**
     * @var string Tax include
     */
    public string $taxInclude;

    /**
     * @return array|string[]
     */
    public function safeAttributes(): array
    {
        return [
            'taxRate',
            'taxName',
            'taxInclude',
        ];
    }

    /**
     * @return string
     */
    public function getTaxRateAsPercent(): string
    {
        return Localization::formatAsPercentage($this->taxRate);
    }
}
