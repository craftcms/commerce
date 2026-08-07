<?php

namespace craft\commerce\services;

use craft\commerce\elements\Variant;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Variants::class)` instead.
 */
class Variants extends Component
{
    /**
     * @return Variant[]
     */
    public function getAllVariantsByProductId(int $productId, ?int $siteId = null, bool $includeDisabled = true): array
    {
        return app(\CraftCms\Commerce\Services\Variants::class)->getAllVariantsByProductId($productId, $siteId, $includeDisabled);
    }

    public function getVariantById(int $variantId, ?int $siteId = null): ?Variant
    {
        return app(\CraftCms\Commerce\Services\Variants::class)->getVariantById($variantId, $siteId);
    }

    /**
     * @throws InvalidConfigException
     */
    public function getVariantGqlContentArguments(): array
    {
        return app(\CraftCms\Commerce\Services\Variants::class)->getVariantGqlContentArguments();
    }
}
