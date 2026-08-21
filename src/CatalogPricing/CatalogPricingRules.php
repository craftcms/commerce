<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing;

use Carbon\Carbon;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\Events\UserAssignedToGroups;
use CraftCms\Commerce\Catalog\Models\CatalogPricingRule;
use CraftCms\Commerce\CatalogPricing\Records\CatalogPricingRule as CatalogPricingRuleRecord;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Promotion\Sales;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function CraftCms\Cms\t;

#[Singleton]
class CatalogPricingRules
{
    private ?bool $hasCatalogPricingRulesCache = null;

    /** @var array<int, Collection<int, CatalogPricingRule>>|null */
    private ?array $allCatalogPricingRules = null;

    public function hasCatalogPricingRules(): bool
    {
        if (!$this->canUseCatalogPricingRules()) {
            return false;
        }

        if ($this->hasCatalogPricingRulesCache === null) {
            $this->hasCatalogPricingRulesCache = $this->query()->exists();
        }

        return (bool) $this->hasCatalogPricingRulesCache;
    }

    public function canUseCatalogPricingRules(): bool
    {
        // TODO: migrate to app(Sales::class)->getAllSales() once Sales service migrated
        if (!empty(app(Sales::class)->getAllSales())) {
            return false;
        }

        return true;
    }

    public function getCatalogPricingRuleById(int $id, ?int $storeId = null): ?CatalogPricingRule
    {
        return $this->getAllCatalogPricingRules($storeId)->firstWhere('id', $id);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllCatalogPricingRules(?int $storeId = null): Collection
    {
        // TODO: migrate to app(Stores::class)->getCurrentStore()->id once Stores service migrated
        $storeId ??= app(Stores::class)->getCurrentStore()->id;

        if ($this->allCatalogPricingRules === null || !isset($this->allCatalogPricingRules[$storeId])) {
            $rows = $this->query()->where('storeId', $storeId)->get()->all();

            $this->allCatalogPricingRules ??= [];
            $this->allCatalogPricingRules[$storeId] = $this->createModels($rows)->keyBy('id');
        }

        return $this->allCatalogPricingRules[$storeId];
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllCatalogPricingRulesByPurchasableId(int $purchasableId, ?int $storeId = null): Collection
    {
        // TODO: migrate to app(Stores::class)->getCurrentStore()->id once Stores service migrated
        $storeId ??= app(Stores::class)->getCurrentStore()->id;

        $rows = $this->query()
            ->whereIn('id', function($sub) use ($purchasableId) {
                $sub->select('catalogPricingRuleId')
                    ->from(Table::CATALOG_PRICING)
                    ->where('purchasableId', $purchasableId);
            })
            ->where('storeId', $storeId)
            ->get()
            ->all();

        return $this->createModels($rows);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllEnabledCatalogPricingRules(?int $storeId = null): Collection
    {
        return $this->getAllCatalogPricingRules($storeId)->filter(fn(CatalogPricingRule $r) => $r->enabled);
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllActiveCatalogPricingRules(?int $storeId = null): Collection
    {
        return $this->getAllEnabledCatalogPricingRules($storeId)->filter(fn(CatalogPricingRule $r) =>
            ($r->dateFrom === null || $r->dateFrom->getTimestamp() <= time()) &&
            ($r->dateTo === null || $r->dateTo->getTimestamp() >= time())
        );
    }

    /**
     * @return Collection<int, CatalogPricingRule>
     */
    public function getAllCatalogPricingRulesWithUserConditions(?int $storeId = null): Collection
    {
        return $this->getAllCatalogPricingRules($storeId)->filter(
            fn(CatalogPricingRule $r) => !empty($r->getCustomerCondition()->getConditionRules())
        );
    }

    public function generateRulePriceFromPrice(?float $basePrice, ?float $basePromotionalPrice, CatalogPricingRule $catalogPricingRule): ?float
    {
        $price = null;

        if ($catalogPricingRule->applyPriceType === CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE) {
            $price = $basePrice;
        } elseif ($catalogPricingRule->applyPriceType === CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PROMOTIONAL_PRICE) {
            if ($basePromotionalPrice === null) {
                return null;
            }
            $price = $basePromotionalPrice;
        }

        if ($price === null) {
            return null;
        }

        return $catalogPricingRule->getRulePriceFromPrice($price);
    }

    public function afterSaveUserHandler(ElementSaved|UserAssignedToGroups $event): void
    {
        if ($event instanceof ElementSaved && !$event->element instanceof User) {
            return;
        }

        // TODO: migrate to app(Stores::class)->getAllStores() once Stores service migrated
        $stores = app(Stores::class)->getAllStores();

        foreach ($stores as $store) {
            $rules = $this->getAllCatalogPricingRulesWithUserConditions($store->id);
            if ($rules->isEmpty()) {
                continue;
            }

            /** @var User $user */
            $user = $event instanceof ElementSaved ? $event->element : Users::getUserById($event->userId);

            $rules->each(function(CatalogPricingRule $rule) use ($user) {
                $customerCondition = $rule->getCustomerCondition();
                if ($customerCondition->matchElement($user)) {
                    $exists = DB::table(Table::CATALOG_PRICING_RULES_USERS)
                        ->where('userId', $user->id)
                        ->where('catalogPricingRuleId', $rule->id)
                        ->exists();

                    if (!$exists) {
                        $now = now()->toDateTimeString();
                        DB::table(Table::CATALOG_PRICING_RULES_USERS)->insert([
                            'userId' => $user->id,
                            'catalogPricingRuleId' => $rule->id,
                            'dateCreated' => $now,
                            'dateUpdated' => $now,
                        ]);
                    }
                } else {
                    DB::table(Table::CATALOG_PRICING_RULES_USERS)
                        ->where('userId', $user->id)
                        ->where('catalogPricingRuleId', $rule->id)
                        ->delete();
                }
            });
        }
    }

    public function saveCatalogPricingRule(CatalogPricingRule $catalogPricingRule, bool $runValidation = true): bool
    {
        $isNew = !$catalogPricingRule->id;

        if ($isNew) {
            $record = new CatalogPricingRuleRecord();
        } else {
            $record = CatalogPricingRuleRecord::find($catalogPricingRule->id);

            if (!$record) {
                throw new \RuntimeException(t('No catalog pricing rule exists with the ID "{id}"', ['id' => $catalogPricingRule->id], category: 'commerce'));
            }
        }

        if ($runValidation && !$catalogPricingRule->validate()) {
            Log::info('Catalog pricing rule not saved due to validation error.');
            return false;
        }

        $record->apply = $catalogPricingRule->apply;
        $record->applyAmount = $catalogPricingRule->applyAmount;
        $record->applyPriceType = $catalogPricingRule->applyPriceType;
        $record->dateFrom = $catalogPricingRule->dateFrom ? Carbon::instance($catalogPricingRule->dateFrom) : null;
        $record->dateTo = $catalogPricingRule->dateTo ? Carbon::instance($catalogPricingRule->dateTo) : null;
        $record->description = $catalogPricingRule->description;
        $record->enabled = $catalogPricingRule->enabled;
        $record->isPromotionalPrice = $catalogPricingRule->isPromotionalPrice;
        $record->name = $catalogPricingRule->name;
        $record->storeId = $catalogPricingRule->storeId;
        $record->metadata = $catalogPricingRule->getMetadata();
        $record->customerCondition = $catalogPricingRule->getCustomerCondition()->getConfig();
        $record->productCondition = $catalogPricingRule->getProductCondition()->getConfig();
        $record->variantCondition = $catalogPricingRule->getVariantCondition()->getConfig();
        $record->purchasableCondition = $catalogPricingRule->getPurchasableCondition()->getConfig();

        DB::beginTransaction();

        try {
            $record->save();
            $catalogPricingRule->id = $record->id;

            DB::table(Table::CATALOG_PRICING_RULES_USERS)
                ->where('catalogPricingRuleId', $catalogPricingRule->id)
                ->delete();

            foreach (array_chunk($catalogPricingRule->getUserIds() ?? [], 1000) as $chunk) {
                $rows = array_map(fn($userId) => [
                    'catalogPricingRuleId' => $catalogPricingRule->id,
                    'userId' => $userId,
                ], $chunk);

                DB::table(Table::CATALOG_PRICING_RULES_USERS)->insert($rows);
            }

            DB::commit();

            // TODO: migrate to app(CatalogPricing::class)->createCatalogPricingJob() once CatalogPricing service is registered
            app(CatalogPricing::class)->createCatalogPricingJob([
                'catalogPricingRuleIds' => [$catalogPricingRule->id],
                'storeId' => $catalogPricingRule->storeId,
            ]);

            $this->clearCaches();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteCatalogPricingRuleById(int $id): bool
    {
        $record = CatalogPricingRuleRecord::find($id);

        if (!$record) {
            return false;
        }

        $this->clearCaches();

        return (bool) $record->delete();
    }

    private function clearCaches(): void
    {
        $this->allCatalogPricingRules = null;
        $this->hasCatalogPricingRulesCache = null;
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::table(Table::CATALOG_PRICING_RULES)
            ->select([
                'apply',
                'applyAmount',
                'applyPriceType',
                'customerCondition',
                'dateCreated',
                'dateFrom',
                'dateTo',
                'dateUpdated',
                'description',
                'enabled',
                'id',
                'isPromotionalPrice',
                'metadata',
                'name',
                'productCondition',
                'purchasableCondition',
                'storeId',
                'variantCondition',
            ]);
    }

    /**
     * @param array<int, object> $rows
     * @return Collection<int, CatalogPricingRule>
     */
    private function createModels(array $rows): Collection
    {
        return collect($rows)->map(function($row) {
            $data = (array) $row;
            $data['customerCondition'] ??= '';
            $data['productCondition'] ??= '';
            $data['purchasableCondition'] ??= '';
            $data['variantCondition'] ??= '';

            return new CatalogPricingRule($data);
        });
    }
}
