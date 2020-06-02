<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

/**
 * PlanInterface defines the common interface to be implemented by plan classes.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @mixin PlanTrait
 */
interface PlanInterface
{
    /**
     * Returns whether it's possible to switch to this plan from a different plan.
     *
     * @param PlanInterface $currentPlant
     * @return bool
     */
    public function canSwitchFrom(PlanInterface $currentPlant): bool;
}
