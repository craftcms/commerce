<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use craft\commerce\elements\Order;
use craft\elements\conditions\ElementCondition;

/**
 * Gateway Order Condition
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.5
 */
class GatewayOrderCondition extends OrderCondition
{
    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        return array_merge(
            parent::conditionRuleTypes(),
            [
                EnabledGatewayConditionRule::class,
            ]
        );
    }
}