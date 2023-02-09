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
     * @var CatalogPricingRule[]|null
     */
    public ?array $catalogPricingRules = null;

    public function execute($queue): void
    {
        Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices($this->purchasableIds, $this->catalogPricingRules, queue: $queue);
    }

    protected function defaultDescription(): ?string
    {
        return 'Generating catalog pricing.';
    }
}
