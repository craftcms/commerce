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
use yii\queue\RetryableJobInterface;

class CatalogPricing extends BaseJob implements RetryableJobInterface
{
    private const MAX_ATTEMPTS = 3;

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

        if (!$isConsolidatedJob) {
            // @TODO: remove these properties and behaviour at next breaking change
            $this->_process($queue, $this->storeId, $this->purchasableIds, $this->catalogPricingRuleIds, null);
            return;
        }

        // New method of processing catalog pricing via queue table: reserve each pending row in turn and process based on its type and IDs
        while ($reservedRecord = $catalogPricingService->reserveCatalogPricingQueueRow()) {
            $purchasableIds = null;
            $catalogPricingRuleIds = null;

            if ($reservedRecord->type === CatalogPricingQueueRecord::TYPE_PURCHASABLE) {
                // Specific purchasable IDs: regenerate against all applicable rules
                $purchasableIds = $reservedRecord->getIds();
            } elseif ($reservedRecord->type === CatalogPricingQueueRecord::TYPE_RULE) {
                $catalogPricingRuleIds = $reservedRecord->getIds();
            } else {
                $catalogPricingService->releaseCatalogPricingQueueRowById($reservedRecord->id);
                throw new \UnexpectedValueException("Unrecognized catalog pricing queue row type: {$reservedRecord->type}");
            }

            $this->_process($queue, $reservedRecord->storeId, $purchasableIds, $catalogPricingRuleIds, $reservedRecord->id);
        }

        if ($catalogPricingService->hasUnreservedCatalogPricingQueueRows()) {
            try {
                QueueHelper::push(Craft::createObject(self::class));
            } catch (\Throwable $e) {
                Craft::error('Unable to re-queue the catalog pricing job: ' . $e->getMessage(), __METHOD__);
                Craft::$app->getErrorHandler()->logException($e);
            }
        }
    }

    private function _process($queue, ?int $storeId, ?array $purchasableIds, ?array $catalogPricingRuleIds, ?int $reservedRowId): void
    {
        $catalogPricingService = Plugin::getInstance()->getCatalogPricing();
        $catalogPricingRules = null;

        if (!empty($catalogPricingRuleIds)) {
            $catalogPricingRules = Plugin::getInstance()->getCatalogPricingRules()
                ->getAllCatalogPricingRules($storeId)
                ->whereIn('id', $catalogPricingRuleIds)
                ->all();

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

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        return Craft::$app->getQueue()->ttr;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return $attempt < self::MAX_ATTEMPTS;
    }

    protected function defaultDescription(): ?string
    {
        return 'Generating catalog pricing.';
    }
}
