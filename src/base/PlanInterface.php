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
 * @todo remove ignore: https://github.com/phpstan/phpstan/issues/6778
 * @phpstan-ignore-next-line
 * @mixin PlanTrait
 */
interface PlanInterface
{
    /**
     * Returns whether it's possible to switch to this plan from a different plan.
     */
    public function canSwitchFrom(PlanInterface $currentPlan): bool;
}
