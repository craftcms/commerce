<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\resolvers\mutations;

use Craft;
use craft\base\Element;
use craft\behaviors\DraftBehavior;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\Product as ProductElement;
use craft\commerce\elements\Variant as VariantElement;
use craft\commerce\models\ProductType;
use craft\gql\base\ElementMutationResolver;
use craft\helpers\ArrayHelper;
use craft\helpers\ElementHelper;
use craft\helpers\Gql;
use GraphQL\Error\Error;
use GraphQL\Error\UserError;
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
    protected array $immutableAttributes = ['id', 'uid', 'draftId', 'typeId', 'siteId', 'sortOrder', 'variants'];

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
        $canIdentify = !empty($arguments['id']) || !empty($arguments['uid']) || !empty($arguments['draftId']);
        $this->requireSchemaAction("productTypes.$productType->uid", $canIdentify ? 'save' : 'create');

        $siteId = $arguments['siteId'] ?? null;
        if ($siteId) {
            $this->requireAllowedSite($siteId);
        } else {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }

        if ($canIdentify) {
            $product = $this->identifyProduct(ProductElement::find()->status(null)->siteId($siteId), $arguments)->one();

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

        $asUnpublishedDraft = !empty($arguments['asUnpublishedDraft']);
        if ($asUnpublishedDraft || array_key_exists('draftId', $arguments)) {
            $product->setScenario(Element::SCENARIO_ESSENTIALS);
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

        if ($asUnpublishedDraft) {
            $this->requireUserAuthorization($product, 'save');
            if (!Craft::$app->getDrafts()->saveElementAsDraft($product, Gql::getSchemaUser()?->id ?? Craft::$app->getUser()->getId(), $arguments['draftName'] ?? null, $arguments['draftNotes'] ?? null)) {
                throw new UserError(implode("\n", $product->getFirstErrors()));
            }
        } else {
            $product = $this->saveElement($product);
        }

        // Refresh the data from the DB
        $query = ProductElement::find()->siteId($siteId)->status(null);
        if ($canIdentify) {
            $this->identifyProduct($query, $arguments);
        } else {
            $query->id($product->id)->drafts($asUnpublishedDraft ? null : false);
        }

        return $query->one();
    }

    /**
     * Creates a draft for the product identified by the passed arguments, and returns the draft ID.
     *
     * @param array $arguments
     * @param ResolveInfo $resolveInfo
     * @return int
     * @throws Throwable
     */
    public function createDraft(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): int
    {
        $product = ProductElement::find()->id($arguments['id'])->status(null)->one();

        if (!$product) {
            throw new Error('Unable to perform the action.');
        }

        $this->requireSchemaAction('productTypes.' . $product->getType()->uid, 'save');
        $this->requireUserAuthorization($product, 'createDrafts');

        // Authenticated users can only create drafts for themselves
        $creatorId = Gql::getSchemaUser()->id ?? $arguments['creatorId'] ?? null;

        /** @var ProductElement|DraftBehavior $draft */
        $draft = Craft::$app->getDrafts()->createDraft(
            $product,
            $creatorId,
            $arguments['name'] ?? '',
            $arguments['notes'] ?? '',
            provisional: $arguments['provisional'] ?? false,
        );

        return $draft->draftId;
    }

    /**
     * Publishes the product draft identified by the passed arguments, and returns the product ID.
     *
     * @param array $arguments
     * @param ResolveInfo $resolveInfo
     * @return int
     * @throws Throwable
     */
    public function publishDraft(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): int
    {
        $draft = ProductElement::find()
            ->status(null)
            ->provisionalDrafts($arguments['provisional'] ?? false)
            ->draftId($arguments['id'])
            ->one();

        if (!$draft) {
            throw new Error('Unable to perform the action.');
        }

        $this->requireSchemaAction('productTypes.' . $draft->getType()->uid, 'save');
        $this->requireUserAuthorization($draft, 'save');
        $this->requireUserAuthorization($draft, 'saveCanonical');

        return Craft::$app->getDrafts()->applyDraft($draft)->id;
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
     * Narrows a product query to the product or draft identified by the passed arguments.
     *
     * @param ProductQuery $query
     * @param array $arguments
     * @return ProductQuery
     */
    private function identifyProduct(ProductQuery $query, array $arguments): ProductQuery
    {
        if (!empty($arguments['draftId'])) {
            $query->draftId($arguments['draftId']);

            if (array_key_exists('provisional', $arguments)) {
                $query->provisionalDrafts($arguments['provisional']);
            }
        } elseif (!empty($arguments['uid'])) {
            $query->uid($arguments['uid']);
        } else {
            $query->id($arguments['id']);
        }

        return $query;
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

                // If this is a draft and the variant belongs to the canonical product, duplicate it as a draft before
                // modifying it, like Matrix fields do. (NestedElementManager will drop its draft status when saving.)
                if (!empty($data) && $product->getIsDerivative() && ElementHelper::belongsToCanonicalOwner($variant, $product)) {
                    $variant = Craft::$app->getDrafts()->createDraft($variant, Gql::getSchemaUser()?->id ?? Craft::$app->getUser()->getId(), null, null, [
                        'canonicalId' => $variant->id,
                        'primaryOwnerId' => $product->id,
                        'owner' => $product,
                        'siteId' => $product->siteId,
                        'propagating' => false,
                        'markAsSaved' => false,
                    ]);
                }
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
