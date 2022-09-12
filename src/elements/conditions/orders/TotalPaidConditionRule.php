<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\orders;

use Craft;

/**
 * Total Paid Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 *
 * @property-read float|int $orderAttributeValue
 */
class TotalPaidConditionRule extends OrderValuesAttributeConditionRule
{
    public string $orderAttribute = 'totalPaid';

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Total Paid');
    }
}
