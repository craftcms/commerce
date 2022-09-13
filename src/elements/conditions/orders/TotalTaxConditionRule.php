<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\orders;

use Craft;

/**
 * Total Tax Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 *
 * @property-read float|int $orderAttributeValue
 */
class TotalTaxConditionRule extends OrderValuesAttributeConditionRule
{
    public string $orderAttribute = 'totalTax';

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Total Tax');
    }

    /**
     * @return string
     */
    protected function inputType(): string
    {
        return 'number';
    }
}
