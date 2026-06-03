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
        $reservedRowId = null;

        // @TODO: remove these properties and behaviour at next breaking change
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
                $purchasableIds = $reservedRecord->getIds();
            } elseif ($reservedRecord->type === CatalogPricingQueueRecord::TYPE_RULE) {
                $catalogPricingRuleIds = $reservedRecord->getIds();
            } else {
                throw new \Exception("CatalogPricing queue rule ids not recognized");
            }
        }

        if (!empty($catalogPricingRuleIds)) {
            $rulesService = Plugin::getInstance()->getCatalogPricingRules();

            if ($storeId !== null) {
                $catalogPricingRules = $rulesService->getAllCatalogPricingRules($storeId)
                    ->whereIn('id', $catalogPricingRuleIds)
                    ->all();
            } else {
                // Rules belong to a single store, but the queue row may span stores —
                // load each rule from its own store so we don't silently drop rules
                // that don't live in the worker's current store.
                $catalogPricingRules = [];
                foreach (Plugin::getInstance()->getStores()->getAllStores() as $store) {
                    foreach ($rulesService->getAllCatalogPricingRules($store->id)->whereIn('id', $catalogPricingRuleIds)->all() as $rule) {
                        $catalogPricingRules[] = $rule;
                    }
                }
            }

            // If the rules were all deleted between enqueueing and processing, bail —
            // an empty rule list combined with null $purchasableIds causes
            // generateCatalogPrices() to truncate the whole catalog pricing table.
            if (empty($catalogPricingRules)) {
                if ($reservedRowId) {
                    $catalogPricingService->deleteCatalogPricingQueueRowById($reservedRowId);
                }
                return;
            }
        }

        try {
            $catalogPricingService->generateCatalogPrices($purchasableIds, $catalogPricingRules, queue: $queue);

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

    protected function defaultDescription(): ?string
    {
        return 'Generating catalog pricing.';
    }
}
