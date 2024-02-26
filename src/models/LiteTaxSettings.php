<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;

/**
 * Class Lite Tax Settings
 *
 * @property-read string $taxRateAsPercent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @deprecated in 4.5.0
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
     * @var bool Tax include
     */
    public bool $taxInclude;

    /**
     * @return array
     */
    public function safeAttributes(): array
    {
        return [
            'taxRate',
            'taxName',
            'taxInclude',
        ];
    }

    public function getTaxRateAsPercent(): string
    {
        return Craft::$app->getFormatter()->asPercent($this->taxRate);
    }

    /**
     * @return array[]
     */
    protected function defineRules(): array
    {
        return [[['taxRate', 'taxName'], 'required']];
    }
}
