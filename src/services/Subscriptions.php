<?php

namespace craft\commerce\services;

use craft\commerce\records\Subscription as SubscriptionRecord;
use yii\base\Component;

/**
 * Susbcriptions service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Subscriptions extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Returns susbcription count for a plan.
     *
     * @param int $planId
     *
     * @return int
     */
    public function getSubscriptionCountForPlanById(int $planId): int
    {
        return SubscriptionRecord::find()->where(['planId' => $planId])->count();
    }

}
