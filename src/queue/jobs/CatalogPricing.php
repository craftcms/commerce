<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\queue\jobs;

use craft\commerce\Plugin;
use craft\commerce\records\CatalogPricingQueue as CatalogPricingQueueRecord;
use craft\queue\BaseJob;

class CatalogPricing extends BaseJob
{
    /**
     * @var array|null
     */
    public ?array $purchasableIds = null;

    /**
     * @var array|null
     */
    public ?array $catalogPricingRuleIds = null;

    /**
     * @var int|null
     */
    public ?int $storeId = null;

    public function execute($queue): void
    {
        $catalogPricingService = Plugin::getInstance()->getCatalogPricing();
        $isConsolidatedJob = $this->storeId === null && $this->purchasableIds === null && $this->catalogPricingRuleIds === null;
        $catalogPricingRules = null;
        $purchasableIds = null;
        $reservedRowId = null;

        if ($isConsolidatedJob) {
            // New method of processing catalog pricing via queue table: reserve a row and process based on its type and IDs
            $reservedRecord = $catalogPricingService->reserveCatalogPricingQueueById();

            if (!$reservedRecord) {
                return;
            }

            $reservedRowId = $reservedRecord->id;
            $storeId = $reservedRecord->storeId;

            if ($reservedRecord->type === CatalogPricingQueueRecord::TYPE_PURCHASABLE) {
                // Specific purchasable IDs: regenerate against all applicable rules
                $purchasableIds = $reservedRecord->getIds();
            } elseif ($reservedRecord->type === CatalogPricingQueueRecord::TYPE_RULE) {
                $catalogPricingRuleIds = $reservedRecord->getIds();
            }
        } else {
            // @TODO: remove these properties and behaviour at next breaking change
            $purchasableIds = $this->purchasableIds;
            $catalogPricingRuleIds = $this->catalogPricingRuleIds;
            $storeId = $this->storeId;
        }

        if (!empty($catalogPricingRuleIds)) {
            $catalogPricingRules = Plugin::getInstance()->getCatalogPricingRules()
                ->getAllCatalogPricingRules($storeId)
                ->whereIn('id', $catalogPricingRuleIds)
                ->all();
        }

        try {
            $catalogPricingService->generateCatalogPrices($purchasableIds, $catalogPricingRules, queue: $queue);

            if ($reservedRowId) {
                $catalogPricingService->deleteCatalogPricingQueueById($reservedRowId);
            }
        } catch (\Throwable $e) {
            if ($reservedRowId) {
                $catalogPricingService->releaseCatalogPricingQueueById($reservedRowId);
            }

            throw $e;
        }
    }

    protected function defaultDescription(): ?string
    {
        return 'Generating catalog pricing.';
    }
}
