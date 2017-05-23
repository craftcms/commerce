<?php
namespace craft\commerce\services;

use craft\commerce\elements\Product;
use craft\commerce\helpers\Db;
use craft\commerce\Plugin;
use craft\commerce\records\Product as ProductRecord;
use yii\base\Component;

/**
 * Product service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Products extends Component
{
    /**
     * @param int $id
     * @param int $localeId
     *
     * @return Product
     */
    public function getProductById($id, $localeId = null)
    {
        return Craft::$app->getElements()->getElementById($id, 'Commerce_Product', $localeId);
    }


    /**
     * @param Product $product
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveProduct(Product $product)
    {

        $isNewProduct = !$product->id;

        if (!$product->id) {
            $record = new ProductRecord();
        } else {
            $record = ProductRecord::findOne($product->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No product exists with the ID “{id}”',
                    ['id' => $product->id]));
            }
        }

        // Fire an 'onBeforeSaveProduct' event
        $event = new Event($this, [
            'product' => $product,
            'isNewProduct' => $isNewProduct
        ]);

        $this->onBeforeSaveProduct($event);

        $record->postDate = $product->postDate;
        $record->expiryDate = $product->expiryDate;
        $record->typeId = $product->typeId;
        $record->promotable = $product->promotable;
        $record->freeShipping = $product->freeShipping;
        $record->taxCategoryId = $product->taxCategoryId;
        $record->shippingCategoryId = $product->shippingCategoryId;

        $record->validate();
        $product->addErrors($record->getErrors());

        $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($product->typeId);

        if (!$productType) {
            throw new Exception(Craft::t('commerce', 'commerce', 'No product type exists with the ID “{id}”',
                ['id' => $product->typeId]));
        }

        $taxCategoryIds = array_keys($productType->getTaxCategories());
        if (!in_array($product->taxCategoryId, $taxCategoryIds)) {
            $record->taxCategoryId = $product->taxCategoryId = $taxCategoryIds[0];
        }

        $shippingCategoryIds = array_keys($productType->getShippingCategories());
        if (!in_array($product->shippingCategoryId, $shippingCategoryIds)) {
            $record->shippingCategoryId = $product->shippingCategoryId = $shippingCategoryIds[0];
        }

        // Final prep of variants and validation
        $variantsValid = true;
        $defaultVariant = null;
        foreach ($product->getVariants() as $variant) {

            // Use the product type's titleFormat if the title field is not shown
            if (!$productType->hasVariantTitleField && $productType->hasVariants) {
                try {
                    $variant->getContent()->title = Craft::$app->getView()->renderObjectTemplate($productType->titleFormat, $variant);
                } catch (\Exception $e) {
                    $variant->getContent()->title = "";
                }
            }

            if (!$productType->hasVariants) {
                // Since VariantModel::getTitle() returns the parent products title when the product has
                // no variants, lets save the products title as the variant title anyway.
                $variant->getContent()->title = $product->getTitle();
            }

            // If we have a blank SKU, generate from product type's skuFormat
            if (!$variant->sku) {
                try {
                    if (!$productType->hasVariants) {
                        $variant->sku = Craft::$app->getView()->renderObjectTemplate($productType->skuFormat, $product);
                    } else {
                        $variant->sku = Craft::$app->getView()->renderObjectTemplate($productType->skuFormat, $variant);
                    }
                } catch (\Exception $e) {
                    $variant->sku = "";
                }
            }

            // Make the first variant (or the last one that says it isDefault) the default.
            if ($defaultVariant === null || $variant->isDefault) {
                $defaultVariant = $variant;
            }

            if (!Plugin::getInstance()->getVariants()->validateVariant($variant)) {
                $variantsValid = false;
                // If we have a title error but hide the title field, put the error onto the sku.
                if ($variant->getFirstError('title') && !$productType->hasVariantTitleField && $productType->hasVariants) {
                    $variant->addError('sku', Craft::t('commerce', 'commerce', 'Could not generate the variant title from product type’s title format.'));
                }

                if ($variant->getFirstError('title') && !$productType->hasVariants) {
                    $product->addError('title', Craft::t('commerce', 'commerce', 'Title cannot be blank.'));
                }
            }
        }

        if ($product->hasErrors() || !$variantsValid) {
            return false;
        }


        Db::beginStackedTransaction();
        try {

            $record->defaultVariantId = $product->defaultVariantId = $defaultVariant->getPurchasableId();
            $record->defaultSku = $product->defaultSku = $defaultVariant->getSku();
            $record->defaultPrice = $product->defaultPrice = $defaultVariant->price * 1;
            $record->defaultHeight = $product->defaultHeight = $defaultVariant->height * 1;
            $record->defaultLength = $product->defaultLength = $defaultVariant->length * 1;
            $record->defaultWidth = $product->defaultWidth = $defaultVariant->width * 1;
            $record->defaultWeight = $product->defaultWeight = $defaultVariant->weight * 1;

            if ($event->performAction) {

                $success = Craft::$app->getElements()->saveElement($product);

                if ($success) {
                    // Now that we have an element ID, save it on the other stuff
                    if ($isNewProduct) {
                        $record->id = $product->id;
                    }

                    $record->save(false);

                    $keepVariantIds = [];
                    $oldVariantIds = Craft::$app->getDb()->createCommand()
                        ->select('id')
                        ->from('commerce_variants')
                        ->where('productId = :productId', [':productId' => $product->id])
                        ->queryColumn();

                    foreach ($product->getVariants() as $variant) {
                        if ($defaultVariant === $variant) {
                            $variant->isDefault = true;
                            $variant->enabled = true; // default must always be enabled.
                        } else {
                            $variant->isDefault = false;
                        }
                        $variant->setProduct($product);

                        Plugin::getInstance()->getVariants()->saveVariant($variant);

                        // Need to manually update the product's default variant ID now that we have a saved ID
                        if ($product->defaultVariantId === null && $defaultVariant === $variant) {
                            $product->defaultVariantId = $variant->id;
                            Craft::$app->getDb()->createCommand()->update('commerce_products', ['defaultVariantId' => $variant->id], ['id' => $product->id]);
                        }

                        $keepVariantIds[] = $variant->id;
                    }

                    foreach (array_diff($oldVariantIds, $keepVariantIds) as $deleteId) {
                        Plugin::getInstance()->getVariants()->deleteVariantById($deleteId);
                    }

                    Db::commitStackedTransaction();
                }
            } else {
                $success = false;
            }
        } catch (\Exception $e) {
            Db::rollbackStackedTransaction();
            throw $e;
        }

        if ($success) {
            // Fire an 'onSaveProduct' event
            $this->onSaveProduct(new Event($this, [
                'product' => $product,
                'isNewProduct' => $isNewProduct
            ]));
        }

        return $success;
    }

    /**
     * This event is raised before a product is saved
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeSaveProduct(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['product']) || !($params['product'] instanceof Product)) {
            throw new Exception('onBeforeSaveProduct event requires "product" param with Product instance that is being saved.');
        }

        if (!isset($params['isNewProduct'])) {
            throw new Exception('onBeforeSaveProduct event requires "isNewProduct" param with a boolean to determine if the product is new.');
        }

        $this->raiseEvent('onBeforeSaveProduct', $event);
    }

    /**
     * This event is raised after a product has been successfully saved
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onSaveProduct(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['product']) || !($params['product'] instanceof Product)) {
            throw new Exception('onSaveProduct event requires "product" param with Product instance that is being saved.');
        }

        if (!isset($params['isNewProduct'])) {
            throw new Exception('onSaveProduct event requires "isNewProduct" param with a boolean to determine if the product is new.');
        }

        $this->raiseEvent('onSaveProduct', $event);
    }

    /**
     * @param Product|Product[] $products
     *
     * @return bool
     * @throws \CDbException
     * @throws \Exception
     */
    public function deleteProduct($products)
    {
        if (!$products) {
            return false;
        }

        $transaction = Craft::$app->getDb()->getCurrentTransaction() === null ? Craft::$app->getDb()->beginTransaction() : null;

        try {
            if (!is_array($products)) {
                $products = [$products];
            }

            $productIds = [];
            $variantsByProductId = [];

            foreach ($products as $product) {
                // Fire an 'onBeforeDeleteProduct' event
                $event = new Event($this, [
                    'product' => $product
                ]);

                $this->onBeforeDeleteProduct($event);

                if ($event->performAction) {
                    $productIds[] = $product->id;
                    $variantsByProductId[$product->id] = Plugin::getInstance()->getVariants()->getAllVariantsByProductId($product->id);
                }
            }

            if ($productIds) {
                // Delete 'em
                $success = Craft::$app->getElements()->deleteElementById($productIds);
            } else {
                $success = false;
            }

            if ($transaction !== null) {
                $transaction->commit();
            }
        } catch (\Exception $e) {
            if ($transaction !== null) {
                $transaction->rollback();
            }

            throw $e;
        }

        if ($success) {
            foreach ($products as $product) {

                // Delete all child variants.
                $variants = $variantsByProductId[$product->id];
                $ids = [];
                foreach ($variants as $v) {
                    $ids[] = $v->id;
                }
                Craft::$app->getElements()->deleteElementById($ids);

                // Fire an 'onDeleteProduct' event
                $this->onDeleteProduct(new Event($this, [
                    'product' => $product
                ]));
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * This event is raised before a product is saved
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeDeleteProduct(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['product']) || !($params['product'] instanceof Product)) {
            throw new Exception('onBeforeDeleteProduct event requires "product" param with Product instance that is being deleted.');
        }

        $this->raiseEvent('onBeforeDeleteProduct', $event);
    }

    /**
     * This event is raised after a product has been successfully saved
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onDeleteProduct(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['product']) || !($params['product'] instanceof Product)) {
            throw new Exception('onDeleteProduct event requires "product" param with Product instance that is being deleted.');
        }

        $this->raiseEvent('onDeleteProduct', $event);
    }
}
