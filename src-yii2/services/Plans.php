<?php

namespace craft\commerce\services;

use craft\commerce\base\Plan;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Subscription\Plans::class)` instead.
 */
class Plans extends Component
{
    public const EVENT_ARCHIVE_PLAN = \CraftCms\Commerce\Subscription\Plans::EVENT_ARCHIVE_PLAN;

    public const EVENT_BEFORE_SAVE_PLAN = \CraftCms\Commerce\Subscription\Plans::EVENT_BEFORE_SAVE_PLAN;

    public const EVENT_AFTER_SAVE_PLAN = \CraftCms\Commerce\Subscription\Plans::EVENT_AFTER_SAVE_PLAN;

    /**
     * @return Plan[]
     */
    public function getAllPlans(): array
    {
        return app(\CraftCms\Commerce\Subscription\Plans::class)->getAllPlans();
    }

    /**
     * @return Plan[]
     */
    public function getAllEnabledPlans(): array
    {
        return app(\CraftCms\Commerce\Subscription\Plans::class)->getAllEnabledPlans();
    }

    /**
     * @return Plan[]
     */
    public function getPlansByGatewayId(int $gatewayId): array
    {
        return app(\CraftCms\Commerce\Subscription\Plans::class)->getPlansByGatewayId($gatewayId);
    }

    public function getPlanById(int $planId): ?Plan
    {
        return app(\CraftCms\Commerce\Subscription\Plans::class)->getPlanById($planId);
    }

    public function getPlanByUid(string $planUid): ?Plan
    {
        return app(\CraftCms\Commerce\Subscription\Plans::class)->getPlanByUid($planUid);
    }

    public function getPlanByHandle(string $handle): ?Plan
    {
        return app(\CraftCms\Commerce\Subscription\Plans::class)->getPlanByHandle($handle);
    }

    public function getPlanByReference(string $reference): ?Plan
    {
        return app(\CraftCms\Commerce\Subscription\Plans::class)->getPlanByReference($reference);
    }

    /**
     * @return Plan[]
     */
    public function getPlansByInformationEntryId(int $entryId): array
    {
        return app(\CraftCms\Commerce\Subscription\Plans::class)->getPlansByInformationEntryId($entryId);
    }

    /**
     * @throws InvalidConfigException if subscription plan not found by id.
     */
    public function savePlan(Plan $plan, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Subscription\Plans::class)->savePlan($plan, $runValidation);
    }

    /**
     * @throws InvalidConfigException
     */
    public function archivePlanById(int $id): bool
    {
        return app(\CraftCms\Commerce\Subscription\Plans::class)->archivePlanById($id);
    }

    /**
     * @param int[] $ids
     */
    public function reorderPlans(array $ids): bool
    {
        return app(\CraftCms\Commerce\Subscription\Plans::class)->reorderPlans($ids);
    }
}
