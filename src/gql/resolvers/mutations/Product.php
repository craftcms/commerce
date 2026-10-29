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
use craft\helpers\ArrayHelper;
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
    protected array $immutableAttributes = ['id', 'uid', 'typeId', 'siteId', 'sortOrder', 'variants'];

    /**
     * Saves a product using the passed arguments.
     *
     * @param array $arguments
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
        $setVariants = isset($arguments['sortOrder']) || isset($arguments['variants']);
        $variantsData = [];
        if (isset($arguments['variants'])) {
            $variantsData = $this->recursivelyNormalizeArgumentValues($resolveInfo, ['variants' => $arguments['variants']])['variants'];
        }

        /** @var ProductElement $product */
        $product = $this->populateElementWithData($product, $arguments, $resolveInfo);

        if ($setVariants) {
            $product->setVariants($this->variants($product, $productType, $arguments['sortOrder'] ?? null, $variantsData));
            $product->setDirtyAttributes(['variants']);
        }

        $product = $this->saveElement($product);

        return ProductElement::find()->id($product->id)->siteId($siteId)->status(null)->one();
    }

    /**
     * Deletes a product identified by the passed arguments.
     *
     * @param array $arguments
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
     * Like Matrix fields, `sortOrder` determines which variants the product should have, and `variants` provides data
     * for new and updated variants, keyed by their IDs or `new:N` (in the order the new variants are listed).
     *
     * @param ProductElement $product
     * @param ProductType $productType
     * @param array|null $sortOrder
     * @param array $variantsData
     * @return VariantElement[]
     * @throws Error
     */
    private function variants(ProductElement $product, ProductType $productType, ?array $sortOrder, array $variantsData): array
    {
        $oldVariantsById = [];
        if ($product->id) {
            foreach ($product->getVariants(true) as $variant) {
                $oldVariantsById[$variant->id] = $variant;
            }
        }

        $newVariantsData = [];
        $newCounter = 1;
        foreach ($variantsData as $data) {
            $id = ArrayHelper::remove($data, 'id') ?? sprintf('new:%s', $newCounter++);
            $newVariantsData[$id] = $data;
        }

        $resolver = new Variant([
            self::CONTENT_FIELD_KEY => array_flip(array_map(
                fn($field) => $field->handle,
                $productType->getVariantFieldLayout()->getCustomFields(),
            )),
        ]);

        $variants = [];

        foreach ($sortOrder ?? array_keys($oldVariantsById) as $id) {
            $data = $newVariantsData[$id] ?? [];

            if (isset($oldVariantsById[$id])) {
                $variant = $oldVariantsById[$id];
            } elseif (str_starts_with((string)$id, 'new')) {
                if (!isset($newVariantsData[$id])) {
                    throw new Error("No variant data was provided for “{$id}”.");
                }
                $variant = new VariantElement();
                $variant->setPrimaryOwner($product);
                $variant->setOwner($product);
            } else {
                throw new Error('No such variant exists on this product');
            }

            $variants[] = $resolver->populateVariant($variant, $data);
        }

        return $variants;
    }
}
