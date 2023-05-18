<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\queue\jobs;

use craft\commerce\models\CatalogPricingRule;
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
        $catalogPricingRules = null;
        if (!empty($this->catalogPricingRuleIds) && $this->storeId) {
            $catalogPricingRules = Plugin::getInstance()->getCatalogPricingRules()->getAllCatalogPricingRules($this->storeId, false)->whereIn('id', $this->catalogPricingRuleIds)->all();
        }

        Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices($this->purchasableIds, $catalogPricingRules, queue: $queue);

        Plugin::getInstance()->getCatalogPricing()->clearCatalogPricingJob($this);
    }

    protected function defaultDescription(): ?string
    {
        return 'Generating catalog pricing.';
    }
}
