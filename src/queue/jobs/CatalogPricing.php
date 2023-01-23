<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\queue\jobs;

use craft\commerce\elements\Order;
use craft\commerce\errors\EmailException;
use craft\commerce\helpers\Locale;
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

    public function execute($queue): void
    {
        Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices($this->purchasableIds, $this->catalogPricingRuleIds, queue: $queue);
    }

    protected function defaultDescription(): ?string
    {
        return 'Generating catalog pricing.';
    }
}
