<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\queue\jobs;

use Craft;
use craft\commerce\Plugin;
use craft\commerce\records\CatalogPricingQueue as CatalogPricingQueueRecord;
use craft\helpers\Queue as QueueHelper;
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

        // @TODO: remove these properties and behaviour at next breaking change
        if (!$isConsolidatedJob) {
            $this->_generate($queue, $this->storeId, $this->purchasableIds, $this->catalogPricingRuleIds);
            return;
        }

        // Work through every pending row. One row per job would leave the rest waiting on a further
        // push, and claiming them all up front would strand the lot if this worker died.
        while ($reservedRecord = $catalogPricingService->reserveCatalogPricingQueueRow()) {
            $purchasableIds = null;
            $catalogPricingRuleIds = null;

            if ($reservedRecord->type === CatalogPricingQueueRecord::TYPE_PURCHASABLE) {
                // Specific purchasable IDs: regenerate against all applicable rules
                $purchasableIds = $reservedRecord->getIds();
            } elseif ($reservedRecord->type === CatalogPricingQueueRecord::TYPE_RULE) {
                $catalogPricingRuleIds = $reservedRecord->getIds();
            } else {
                throw new \UnexpectedValueException("Unrecognized catalog pricing queue row type: {$reservedRecord->type}");
            }

            try {
                $this->_generate($queue, $reservedRecord->storeId, $purchasableIds, $catalogPricingRuleIds);
                $catalogPricingService->deleteCatalogPricingQueueRowById($reservedRecord->id);
            } catch (\Throwable $e) {
                $catalogPricingService->releaseCatalogPricingQueueRowById($reservedRecord->id);

                throw $e;
            }
        }

        // A row added between the last check and here would otherwise wait for the next save.
        if ($catalogPricingService->hasPendingCatalogPricingQueueRows()) {
            QueueHelper::push(Craft::createObject(self::class));
        }
    }

    /**
     * @param array<int>|null $purchasableIds
     * @param array<int>|null $catalogPricingRuleIds
     */
    private function _generate($queue, ?int $storeId, ?array $purchasableIds, ?array $catalogPricingRuleIds): void
    {
        $catalogPricingRules = null;

        if (!empty($catalogPricingRuleIds)) {
            $catalogPricingRules = Plugin::getInstance()->getCatalogPricingRules()
                ->getAllCatalogPricingRules($storeId)
                ->whereIn('id', $catalogPricingRuleIds)
                ->all();
        }

        Plugin::getInstance()->getCatalogPricing()
            ->generateCatalogPrices($purchasableIds, $catalogPricingRules, queue: $queue);
    }

    protected function defaultDescription(): ?string
    {
        return 'Generating catalog pricing.';
    }
}
