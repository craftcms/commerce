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
 * Class Variant
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Variant extends ElementMutationResolver
{
    /** @inheritdoc */
    protected array $immutableAttributes = ['id', 'uid', 'productId', 'siteId'];

    /**
     * Saves a variant using the passed arguments.
     *
     * @param array $arguments
     * @param ResolveInfo $resolveInfo
     * @return VariantElement
     * @throws Throwable
     */
    public function saveVariant(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): VariantElement
    {
        /** @var ProductType $productType */
        $productType = $this->getResolutionData('productType');
        $this->requireSchemaAction("productTypes.$productType->uid", 'save');

        $siteId = $arguments['siteId'] ?? null;
        if ($siteId) {
            $this->requireAllowedSite($siteId);
        } else {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }

        if (!empty($arguments['id']) || !empty($arguments['uid'])) {
            $query = VariantElement::find()->status(null)->siteId($siteId);
            if (!empty($arguments['uid'])) {
                $query->uid($arguments['uid']);
            } else {
                $query->id($arguments['id']);
            }

            $variant = $query->one();

            if (!$variant) {
                throw new Error('No such variant exists');
            }

            $product = $variant->getOwner();
        } else {
            if (empty($arguments['productId'])) {
                throw new Error('A product ID is required to create a variant.');
            }

            $product = ProductElement::find()->id($arguments['productId'])->siteId($siteId)->status(null)->one();

            if (!$product) {
                throw new Error('No such product exists');
            }

            $variant = new VariantElement();
            $variant->setPrimaryOwner($product);
            $variant->setOwner($product);
        }

        if ($product?->typeId !== $productType->id) {
            throw new Error(sprintf('The variant’s product isn’t in the “%s” product type.', $productType->name));
        }

        $variant = $this->populateElementWithData($variant, $arguments, $resolveInfo);
        $variant = $this->saveElement($variant);

        return VariantElement::find()->id($variant->id)->siteId($siteId)->status(null)->one();
    }

    /**
     * Populates a variant with data submitted as part of its product’s save mutation.
     *
     * The data should already be normalized.
     *
     * @param VariantElement $variant
     * @param array $data
     * @return VariantElement
     */
    public function populateVariant(VariantElement $variant, array $data): VariantElement
    {
        return $this->populateElementWithData($variant, $data);
    }

    /**
     * Deletes a variant identified by the passed arguments.
     *
     * @param array $arguments
     * @param ResolveInfo $resolveInfo
     * @return bool
     * @throws Throwable
     */
    public function deleteVariant(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): bool
    {
        $variant = VariantElement::find()->id($arguments['id'])->status(null)->one();

        if (!$variant) {
            return false;
        }

        $this->requireSchemaAction('productTypes.' . $variant->getOwner()->getType()->uid, 'delete');
        $this->requireUserAuthorization($variant, 'delete');

        return Craft::$app->getElements()->deleteElement($variant, $arguments['hardDelete'] ?? false);
    }
}
