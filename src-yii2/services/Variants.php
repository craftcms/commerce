<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Catalog\Elements\Variant;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Catalog\Variants::class)` instead.
 */
class Variants extends Component
{
    /**
     * @return Variant[]
     */
    public function getAllVariantsByProductId(int $productId, ?int $siteId = null, bool $includeDisabled = true): array
    {
        return app(\CraftCms\Commerce\Catalog\Variants::class)->getAllVariantsByProductId($productId, $siteId, $includeDisabled);
    }

    public function getVariantById(int $variantId, ?int $siteId = null): ?Variant
    {
        return app(\CraftCms\Commerce\Catalog\Variants::class)->getVariantById($variantId, $siteId);
    }

    /**
     * @throws InvalidConfigException
     */
    public function getVariantGqlContentArguments(): array
    {
        return app(\CraftCms\Commerce\Catalog\Variants::class)->getVariantGqlContentArguments();
    }
}
