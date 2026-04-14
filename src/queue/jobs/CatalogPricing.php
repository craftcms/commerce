<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\queue\jobs;

use craft\commerce\Plugin;
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
        $reservedRow = $catalogPricingService->reserveCatalogPricingQueueRow();

        if (!$reservedRow) {
            return;
        }

        $purchasableIds = $reservedRow['purchasableIds'] ?? $this->purchasableIds;
        $catalogPricingRuleIds = $reservedRow['catalogPricingRuleIds'] ?? $this->catalogPricingRuleIds;
        $storeId = $reservedRow['storeId'] ?? $this->storeId;

        $catalogPricingRules = null;
        if (!empty($catalogPricingRuleIds)) {
            $catalogPricingRules = Plugin::getInstance()->getCatalogPricingRules()
                ->getAllCatalogPricingRules($storeId)
                ->whereIn('id', $catalogPricingRuleIds)
                ->all();
        }

        try {
            $catalogPricingService->generateCatalogPrices($purchasableIds, $catalogPricingRules, queue: $queue);

            if (!empty($reservedRow['id'])) {
                $catalogPricingService->deleteCatalogPricingQueueRow((int)$reservedRow['id']);
            }
        } catch (\Throwable $e) {
            if (!empty($reservedRow['id'])) {
                $catalogPricingService->releaseCatalogPricingQueueRow((int)$reservedRow['id']);
            }

            throw $e;
        }
    }

    protected function defaultDescription(): ?string
    {
        return 'Generating catalog pricing.';
    }
}
