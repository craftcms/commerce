<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\subscriptions;

use craft\commerce\base\Plan;
use craft\commerce\base\PlanInterface;

/**
 * Class DummyPlan
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DummyPlan extends Plan
{
    /**
     * @inheritdoc
     * @todo Fix typo: rename $currentPlant parameter to $currentPlan in Commerce 6.0
     */
    public function canSwitchFrom(PlanInterface $currentPlant): bool
    {
        return true;
    }
}
