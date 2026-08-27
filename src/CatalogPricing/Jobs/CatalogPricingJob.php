<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing\Jobs;

use CraftCms\Cms\Queue\Job;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingQueue as CatalogPricingQueueRecord;

class CatalogPricingJob extends Job
{
    public function __construct(
        public readonly ?array $purchasableIds = null,
        public readonly ?array $catalogPricingRuleIds = null,
        public readonly ?int $storeId = null,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $catalogPricingService = app(CatalogPricing::class);
        $isConsolidatedJob = $this->storeId === null && $this->purchasableIds === null && $this->catalogPricingRuleIds === null;
        $catalogPricingRules = null;
        $reservedRowId = null;

        $storeId = $this->storeId;
        $purchasableIds = $this->purchasableIds;
        $catalogPricingRuleIds = $this->catalogPricingRuleIds;

        if ($isConsolidatedJob) {
            // New method of processing catalog pricing via queue table: reserve a row and process based on its type and IDs
            $reservedRecord = $catalogPricingService->reserveCatalogPricingQueueRow();

            if (!$reservedRecord) {
                return;
            }

            $reservedRowId = $reservedRecord->id;
            $storeId = $reservedRecord->storeId;

            if ($reservedRecord->type === CatalogPricingQueueRecord::TYPE_PURCHASABLE) {
                // Specific purchasable IDs: regenerate against all applicable rules
                $purchasableIds = $reservedRecord->ids;
            } elseif ($reservedRecord->type === CatalogPricingQueueRecord::TYPE_RULE) {
                $catalogPricingRuleIds = $reservedRecord->ids;
            } else {
                throw new \UnexpectedValueException("Unrecognized catalog pricing queue row type: {$reservedRecord->type}");
            }
        }

        if (!empty($catalogPricingRuleIds)) {
            $catalogPricingRules = app(CatalogPricingRules::class)
                ->getAllCatalogPricingRules($storeId)
                ->whereIn('id', $catalogPricingRuleIds)
                ->all();
        }

        try {
            $catalogPricingService->generateCatalogPrices($purchasableIds, $catalogPricingRules, queue: $this);

            if ($reservedRowId) {
                $catalogPricingService->deleteCatalogPricingQueueRowById($reservedRowId);
            }
        } catch (\Throwable $e) {
            if ($reservedRowId) {
                $catalogPricingService->releaseCatalogPricingQueueRowById($reservedRowId);
            }

            throw $e;
        }
    }

    /**
     * Widened to public so {@see CatalogPricing::generateCatalogPrices()} can report progress
     * on this job via its duck-typed `$queue` parameter.
     */
    #[\Override]
    public function setProgress(int $progress, ?string $label = null): void
    {
        parent::setProgress($progress, $label);
    }

    #[\Override]
    protected function defaultDescription(): string
    {
        return 'Generating catalog pricing.';
    }
}
