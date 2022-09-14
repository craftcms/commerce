<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\orders;

use Craft;

/**
 * Total Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 *
 * @property-read float|int $orderAttributeValue
 */
class TotalConditionRule extends OrderCurrencyValuesAttributeConditionRule
{
    public string $orderAttribute = 'total';

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Total');
    }
}
