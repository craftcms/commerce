<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\resolvers\mutations;

use Craft;
use craft\commerce\elements\Product as ProductElement;
use craft\commerce\elements\Variant as VariantElement;
use craft\commerce\models\ProductType;
use craft\gql\base\ElementMutationResolver;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Throwable;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Product extends ElementMutationResolver
{
    /** @inheritdoc */
    protected array $immutableAttributes = ['id', 'uid', 'typeId', 'siteId', 'variants'];

    /**
     * Saves a product using the passed arguments.
     *
     * @param mixed $source
     * @param array $arguments
     * @param mixed $context
     * @param ResolveInfo $resolveInfo
     * @return ProductElement
     * @throws Throwable
     */
    public function saveProduct(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): ProductElement
    {
        /** @var ProductType $productType */
        $productType = $this->getResolutionData('productType');
        $canIdentify = !empty($arguments['id']) || !empty($arguments['uid']);
        $this->requireSchemaAction("productTypes.$productType->uid", $canIdentify ? 'save' : 'create');

        $siteId = $arguments['siteId'] ?? null;
        if ($siteId) {
            $this->requireAllowedSite($siteId);
        } else {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }

        if ($canIdentify) {
            $query = ProductElement::find()->status(null)->siteId($siteId);
            if (!empty($arguments['uid'])) {
                $query->uid($arguments['uid']);
            } else {
                $query->id($arguments['id']);
            }

            $product = $query->one();

            if (!$product) {
                throw new Error('No such product exists');
            }

            if ($product->typeId !== $productType->id) {
                throw new Error('Impossible to change the product type of an existing product');
            }
        } else {
            $product = new ProductElement([
                'typeId' => $productType->id,
                'siteId' => $siteId,
            ]);
        }

        // Normalize the variant data along with the product’s, but set it on the product separately
        $variantsData = null;
        if (isset($arguments['variants'])) {
            $variantsData = $this->recursivelyNormalizeArgumentValues($resolveInfo, ['variants' => $arguments['variants']])['variants'];
        }

        /** @var ProductElement $product */
        $product = $this->populateElementWithData($product, $arguments, $resolveInfo);

        if ($variantsData !== null) {
            $product->setVariants($this->variants($product, $productType, $variantsData));
            $product->setDirtyAttributes(['variants']);
        }

        $product = $this->saveElement($product);

        return ProductElement::find()->id($product->id)->siteId($siteId)->status(null)->one();
    }

    /**
     * Deletes a product identified by the passed arguments.
     *
     * @param mixed $source
     * @param array $arguments
     * @param mixed $context
     * @param ResolveInfo $resolveInfo
     * @return bool
     * @throws Throwable
     */
    public function deleteProduct(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): bool
    {
        $product = ProductElement::find()->id($arguments['id'])->status(null)->one();

        if (!$product) {
            return false;
        }

        $this->requireSchemaAction('productTypes.' . $product->getType()->uid, 'delete');
        $this->requireUserAuthorization($product, 'delete');

        return Craft::$app->getElements()->deleteElement($product, $arguments['hardDelete'] ?? false);
    }

    /**
     * Returns the product’s variants, populated with the submitted variant data.
     *
     * @param ProductElement $product
     * @param ProductType $productType
     * @param array $variantsData
     * @return VariantElement[]
     * @throws Error
     */
    private function variants(ProductElement $product, ProductType $productType, array $variantsData): array
    {
        $existingVariants = $product->id ? $product->getVariants(true)->all() : [];

        $resolver = new Variant([
            self::CONTENT_FIELD_KEY => array_flip(array_map(
                fn($field) => $field->handle,
                $productType->getVariantFieldLayout()->getCustomFields(),
            )),
        ]);

        $variants = [];

        foreach ($variantsData as $data) {
            if (!empty($data['id']) || !empty($data['uid'])) {
                $variant = null;
                foreach ($existingVariants as $existingVariant) {
                    if (
                        (!empty($data['uid']) && $existingVariant->uid === $data['uid']) ||
                        (empty($data['uid']) && $existingVariant->id == $data['id'])
                    ) {
                        $variant = $existingVariant;
                        break;
                    }
                }

                if (!$variant) {
                    throw new Error('No such variant exists on this product');
                }
            } else {
                $variant = new VariantElement();
                $variant->setPrimaryOwner($product);
                $variant->setOwner($product);
            }

            $variants[] = $resolver->populateVariant($variant, $data);
        }

        return $variants;
    }
}
