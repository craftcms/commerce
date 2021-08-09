<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\base\GqlInlineFragmentFieldInterface;
use craft\commerce\elements\Variant;
use craft\commerce\helpers\Gql as GqlCommerceHelper;
use craft\commerce\Plugin;
use craft\gql\types\QueryArgument;
use GraphQL\Type\Definition\Type;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Variant service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Variants extends Component
{
    /**
     * @var array
     * @since 3.1.4
     */
    private array $_contentFieldCache = [];

    /**
     * Returns a product's variants, per the product's ID.
     *
     * @param int $productId product ID
     * @param int|null $siteId Site ID for which to return the variants. Defaults to `null` which is current site.
     * @return Variant[]
     */
    public function getAllVariantsByProductId(int $productId, int $siteId = null): array
    {
        $variants = Variant::find()->productId($productId)->anyStatus()->limit(null)->siteId($siteId)->all();

        foreach ($variants as $variant) {
            $variant->typecastAttributes();
        }

        return $variants;
    }

    /**
     * Returns a variant by its ID.
     *
     * @param int $variantId The variant’s ID.
     * @param int|null $siteId The site ID for which to fetch the variant. Defaults to `null` which is current site.
     * @return Variant|null
     */
    public function getVariantById(int $variantId, int $siteId = null): ?Variant
    {
        /** @var Variant|null $variant */
        $variant = Craft::$app->getElements()->getElementById($variantId, Variant::class, $siteId);

        if ($variant) {
            $variant->typecastAttributes();
        }

        return $variant;
    }

    /**
     * @return array
     * @throws InvalidConfigException
     * @since 3.1.4
     */
    public function getVariantGqlContentArguments(): array
    {
        if (empty($this->_contentFieldCache)) {
            $contentArguments = [];

            foreach (Plugin::getInstance()->getProductTypes()->getAllProductTypes() as $productType) {
                if (!$productType->hasVariants) {
                    continue;
                }

                if (!GqlCommerceHelper::isSchemaAwareOf(Variant::gqlScopesByContext($productType))) {
                    continue;
                }

                $fieldLayout = $productType->getVariantFieldLayout();
                foreach ($fieldLayout->getFields() as $contentField) {
                    if (!$contentField instanceof GqlInlineFragmentFieldInterface) {
                        $contentArguments[$contentField->handle] = [
                            'name' => $contentField->handle,
                            'type' => Type::listOf(QueryArgument::getType()),
                        ];
                    }
                }
            }

            $this->_contentFieldCache = $contentArguments;
        }

        return $this->_contentFieldCache;
    }
}
