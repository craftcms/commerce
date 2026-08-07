<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\base\Plan;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\Plugin;
use craft\commerce\records\Plan as PlanRecord;
use craft\helpers\Db as CraftDb;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Subscription\Events\PlanEvent;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use yii\base\InvalidConfigException;
use function CraftCms\Cms\t;

#[Singleton]
class Plans
{
    public const string EVENT_ARCHIVE_PLAN = 'archivePlan';

    public const string EVENT_BEFORE_SAVE_PLAN = 'beforeSavePlan';

    public const string EVENT_AFTER_SAVE_PLAN = 'afterSavePlan';

    /**
     * Memoized array of plans.
     *
     * @var Plan[]|null
     */
    private ?array $allPlans = null;

    /**
     * Returns all subscription plans.
     *
     * @return Plan[]
     */
    public function getAllPlans(): array
    {
        return collect($this->getAllPlansMemoized())->where('isArchived', false)->all();
    }

    /**
     * Returns all enabled subscription plans.
     *
     * @return Plan[]
     */
    public function getAllEnabledPlans(): array
    {
        return collect($this->getAllPlansMemoized())
            ->where('enabled', true)
            ->where('isArchived', false)
            ->all();
    }

    /**
     * Return all subscription plans for a gateway.
     *
     * @return Plan[]
     */
    public function getPlansByGatewayId(int $gatewayId): array
    {
        return collect($this->getAllPlansMemoized())
            ->where('gatewayId', $gatewayId)
            ->where('isArchived', false)
            ->all();
    }

    /**
     * Returns a subscription plan by its id.
     */
    public function getPlanById(int $planId): ?Plan
    {
        return collect($this->getAllPlansMemoized())->firstWhere('id', $planId);
    }

    /**
     * Returns a subscription plan by its uid.
     */
    public function getPlanByUid(string $planUid): ?Plan
    {
        return collect($this->getAllPlansMemoized())->firstWhere('uid', $planUid);
    }

    /**
     * Returns a subscription plan by its handle.
     */
    public function getPlanByHandle(string $handle): ?Plan
    {
        return collect($this->getAllPlansMemoized())
            ->where('handle', $handle)
            ->where('isArchived', false)
            ->first();
    }

    /**
     * Returns a subscription plan by its reference.
     */
    public function getPlanByReference(string $reference): ?Plan
    {
        return collect($this->getAllPlansMemoized())->firstWhere('reference', $reference);
    }

    /**
     * Returns plans which use the provided Entry for its "information".
     *
     * @return Plan[]
     */
    public function getPlansByInformationEntryId(int $entryId): array
    {
        return collect($this->getAllPlansMemoized())->where('planInformationId', $entryId)->all();
    }

    /**
     * Save a subscription plan.
     *
     * @throws InvalidConfigException if subscription plan not found by id.
     */
    public function savePlan(Plan $plan, bool $runValidation = true): bool
    {
        if ($plan->id) {
            /** @phpstan-ignore-next-line */
            $record = PlanRecord::findOne($plan->id);

            if (!$record) {
                throw new InvalidConfigException(t('No subscription plan exists with the ID "{id}"', ['id' => $plan->id], category: 'commerce'));
            }
        } else {
            $record = new PlanRecord();
        }

        // Raise 'beforeSavePlan' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPlans()->hasEventHandlers(self::EVENT_BEFORE_SAVE_PLAN)) {
            $beforeEvent = new PlanEvent();
            $beforeEvent->plan = $plan;
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPlans()->trigger(self::EVENT_BEFORE_SAVE_PLAN, $beforeEvent);
        }

        if ($runValidation && !$plan->validate()) {
            Log::info('Subscription plan not saved due to validation error.');

            return false;
        }

        /** @phpstan-ignore-next-line */
        $record->gatewayId = $plan->gatewayId;
        /** @phpstan-ignore-next-line */
        $record->name = $plan->name;
        /** @phpstan-ignore-next-line */
        $record->handle = $plan->handle;
        /** @phpstan-ignore-next-line */
        $record->planInformationId = $plan->planInformationId;
        /** @phpstan-ignore-next-line */
        $record->reference = $plan->reference;
        /** @phpstan-ignore-next-line */
        $record->planData = $plan->planData;
        /** @phpstan-ignore-next-line */
        $record->enabled = $plan->enabled;
        /** @phpstan-ignore-next-line */
        $record->isArchived = $plan->isArchived;
        /** @phpstan-ignore-next-line */
        $record->dateArchived = CraftDb::prepareDateForDb($plan->dateArchived);
        /** @phpstan-ignore-next-line */
        $record->sortOrder = $plan->sortOrder ?? 99;

        /** @phpstan-ignore-next-line */
        $record->save(false);

        // Now that we have a record ID, save it on the model
        /** @phpstan-ignore-next-line */
        $plan->id = $record->id;

        // Raise 'afterSavePlan' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPlans()->hasEventHandlers(self::EVENT_AFTER_SAVE_PLAN)) {
            $afterEvent = new PlanEvent();
            $afterEvent->plan = $plan;
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPlans()->trigger(self::EVENT_AFTER_SAVE_PLAN, $afterEvent);
        }

        // Reset cache/memoization
        $this->allPlans = null;

        return true;
    }

    /**
     * Archive a subscription plan by its id.
     */
    public function archivePlanById(int $id): bool
    {
        $plan = $this->getPlanById($id);

        if (!$plan) {
            return false;
        }

        // Raise 'archivePlan' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPlans()->hasEventHandlers(self::EVENT_ARCHIVE_PLAN)) {
            $event = new PlanEvent();
            $event->plan = $plan;
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPlans()->trigger(self::EVENT_ARCHIVE_PLAN, $event);
        }

        $plan->isArchived = true;
        $plan->dateArchived = \craft\helpers\DateTimeHelper::now();

        return $this->savePlan($plan);
    }

    /**
     * Reorders subscription plans by ids.
     *
     * @param int[] $ids Array of plans.
     */
    public function reorderPlans(array $ids): bool
    {
        foreach ($ids as $planOrder => $planId) {
            DB::table(Table::PLANS)->where('id', $planId)->update(['sortOrder' => $planOrder + 1]);
        }

        // Reset cache/memoization
        $this->allPlans = null;

        return true;
    }

    /**
     * Populate a payment plan model from database table row.
     *
     * @throws InvalidConfigException if the gateway does not support subscriptions
     */
    private function populatePlan(array $result): Plan
    {
        $gateway = Plugin::getInstance()->getGateways()->getGatewayById($result['gatewayId']);

        if (!$gateway instanceof SubscriptionGateway) {
            throw new InvalidConfigException('This gateway does not support subscriptions');
        }

        $plan = $gateway->getPlanModel();

        $plan->setAttributes($result, false);

        return $plan;
    }

    /**
     * Get all plans memoized.
     *
     * @return Plan[]
     */
    private function getAllPlansMemoized(): array
    {
        if ($this->allPlans === null) {
            $this->allPlans = [];

            $results = DB::table(Table::PLANS . ' as p')
                ->select([
                    'p.dateArchived',
                    'p.dateCreated',
                    'p.dateUpdated',
                    'p.enabled',
                    'p.gatewayId',
                    'p.handle',
                    'p.id',
                    'p.isArchived',
                    'p.name',
                    'p.planData',
                    'p.planInformationId',
                    'p.reference',
                    'p.sortOrder',
                    'p.uid',
                ])
                ->join(Table::GATEWAYS . ' as g', 'g.id', '=', 'p.gatewayId')
                ->where('g.isArchived', false)
                ->orderBy('p.sortOrder')
                ->get();

            foreach ($results as $result) {
                try {
                    $plan = $this->populatePlan((array)$result);
                    $this->allPlans[$plan->id] = $plan;
                } catch (InvalidConfigException) {
                    continue; // Just skip this
                }
            }
        }

        return $this->allPlans;
    }
}
