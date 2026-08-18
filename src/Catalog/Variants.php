<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog;

use craft\commerce\Plugin;
use craft\gql\types\QueryArgument;
use CraftCms\Cms\Gql\Contracts\GqlInlineFragmentFieldInterface;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Helpers\Gql as GqlCommerceHelper;
use GraphQL\Type\Definition\Type;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class Variants
{
    private array $contentFieldCache = [];

    /**
     * Returns a product's variants, per the product's ID.
     *
     * @param int|null $siteId Site ID for which to return the variants. Defaults to `null` which is current site.
     * @return Variant[]
     */
    public function getAllVariantsByProductId(int $productId, ?int $siteId = null, bool $includeDisabled = true): array
    {
        $variantQuery = Variant::find()
            ->productId($productId)
            ->limit(null)
            ->siteId($siteId);

        if ($includeDisabled) {
            $variantQuery->status(null);
        }

        return $variantQuery->all();
    }

    /**
     * Returns a variant by its ID.
     *
     * @param int|null $siteId The site ID for which to fetch the variant. Defaults to `null` which is current site.
     */
    public function getVariantById(int $variantId, ?int $siteId = null): ?Variant
    {
        return Elements::getElementById($variantId, Variant::class, $siteId);
    }

    /**
     * @throws \RuntimeException
     */
    public function getVariantGqlContentArguments(): array
    {
        if (empty($this->contentFieldCache)) {
            $contentArguments = [];

            foreach (Plugin::getInstance()->getProductTypes()->getAllProductTypes() as $productType) {
                if (!GqlCommerceHelper::isSchemaAwareOf(Variant::gqlScopesByContext($productType))) {
                    continue;
                }

                $fieldLayout = $productType->getVariantFieldLayout();
                foreach ($fieldLayout->getCustomFields() as $contentField) {
                    if (!$contentField instanceof GqlInlineFragmentFieldInterface) {
                        $contentArguments[$contentField->handle] = [
                            'name' => $contentField->handle,
                            'type' => Type::listOf(QueryArgument::getType()),
                        ];
                    }
                }
            }

            $this->contentFieldCache = $contentArguments;
        }

        return $this->contentFieldCache;
    }
}
