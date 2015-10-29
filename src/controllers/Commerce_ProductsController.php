<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;
use Commerce\Helpers\CommerceVariantMatrixHelper as VariantMatrixHelper;

/**
 * Class Commerce_ProductsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ProductsController extends Commerce_BaseCpController
{
    /** @var bool All product changes should be by a logged in user */
    protected $allowAnonymous = false;

    /**
     * Index of products
     */
    public function actionProductIndex(array $variables = [])
    {
        $this->renderTemplate('commerce/products/_index', $variables);
    }

    /**
     * Prepare screen to edit a product.
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEditProduct(array $variables = [])
    {
        $this->_prepProductVariables($variables);

        if (!empty($variables['product']->id)) {
            $variables['title'] = $variables['product']->title;
        } else {
            $variables['title'] = Craft::t('Create a new product');
        }

        $variables['continueEditingUrl'] = "commerce/products/" . $variables['productTypeHandle'] . "/{id}-{slug}" .
            (craft()->isLocalized() && craft()->getLanguage() != $variables['localeId'] ? '/' . $variables['localeId'] : '');

        $this->_prepVariables($variables);

        if ($variables['product']->getType()->hasVariants) {
            $variables['variantMatrixHtml'] = VariantMatrixHelper::getVariantMatrixHtml($variables['product']);
        }

        craft()->templates->includeCssResource('commerce/product.css');
        $this->renderTemplate('commerce/products/_edit', $variables);
    }

    private function _prepProductVariables(&$variables)
    {
        if (craft()->isLocalized()) {
            $variables['localeIds'] = craft()->i18n->getEditableLocaleIds();
        }

        // If the user has access to no locales, still give them the default
        if(!count($variables['localeIds'])){
            $variables['localeIds'] = [craft()->i18n->getPrimarySiteLocaleId()];
        }

        if (empty($variables['localeId'])) {
            $variables['localeId'] = craft()->language;

            if (!in_array($variables['localeId'], $variables['localeIds'])) {
                $variables['localeId'] = $variables['localeIds'][0];
            }
        } else {
            // Make sure they were requesting a valid locale
            if (!in_array($variables['localeId'], $variables['localeIds'])) {
                throw new HttpException(404);
            }
        }

        if (!empty($variables['productTypeHandle'])) {
            $variables['productType'] = craft()->commerce_productTypes->getByHandle($variables['productTypeHandle']);
        }

        if (empty($variables['productType'])) {
            throw new HttpException(400,
                craft::t('Wrong product type specified'));
        }

        if (empty($variables['product'])) {
            if (!empty($variables['productId'])) {
                $variables['product'] = craft()->commerce_products->getById($variables['productId'], $variables['localeId']);

                if (!$variables['product']->id) {
                    throw new HttpException(404);
                }
            } else {
                $variables['product'] = new Commerce_ProductModel();
                $variables['product']->typeId = $variables['productType']->id;
                if ($variables['localeId']) {
                    $variables['product']->locale = $variables['localeId'];
                }
            }
        }

        if (!empty($variables['product']->id)) {
            $variables['enabledLocales'] = craft()->elements->getEnabledLocalesForElement($variables['product']->id);
        } else {
            $variables['enabledLocales'] = [];

            foreach (craft()->i18n->getEditableLocaleIds() as $locale) {
                $variables['enabledLocales'][] = $locale;
            }
        }
    }

    /**
     * @param $variables
     *
     * @throws HttpException
     */
    private function _prepVariables(&$variables)
    {
        $variables['tabs'] = [];

        foreach ($variables['productType']->getFieldLayout()->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;
            if ($variables['product']->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    if ($variables['product']->getErrors($field->getField()->handle)) {
                        $hasErrors = true;
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Craft::t($tab->name),
                'url' => '#tab' . ($index + 1),
                'class' => ($hasErrors ? 'error' : null)
            ];
        }

        $variables['primaryVariant'] = ArrayHelper::getFirstValue($variables['product']->getVariants());
    }

    /**
     * Deletes a product.
     *
     * @throws Exception if you try to edit a non existing Id.
     */
    public function actionDeleteProduct()
    {
        $this->requirePostRequest();

        $productId = craft()->request->getRequiredPost('productId');
        $product = craft()->commerce_products->getById($productId);

        if (!$product->id) {
            throw new Exception(Craft::t('No product exists with the ID “{id}”.',
                ['id' => $productId]));
        }

        if (craft()->commerce_products->delete($product)) {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson(['success' => true]);
            } else {
                craft()->userSession->setNotice(Craft::t('Product deleted.'));
                $this->redirectToPostedUrl($product);
            }
        } else {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson(['success' => false]);
            } else {
                craft()->userSession->setError(Craft::t('Couldn’t delete product.'));

                craft()->urlManager->setRouteVariables([
                    'product' => $product

                ]);
            }
        }
    }

    /**
     * Save a new or existing product.
     */
    public function actionSaveProduct()
    {
        $this->requirePostRequest();

        $product = $this->_setProductFromPost();
        $this->_setVariantsFromPost($product);

        $existingProduct = (bool)$product->id;

        CommerceDbHelper::beginStackedTransaction();

        if (craft()->commerce_products->save($product)) {

            CommerceDbHelper::commitStackedTransaction();

            craft()->userSession->setNotice(Craft::t('Product saved.'));

            $this->redirectToPostedUrl($product);
        }

        CommerceDbHelper::rollbackStackedTransaction();
        // Since Product may have been ok to save and an ID assigned,
        // but child model validation failed and the transaction rolled back.
        // Since action failed, lets remove the ID that was no persisted.
        if (!$existingProduct) {
            $product->id = null;
        }


        craft()->userSession->setNotice(Craft::t("Couldn't save product."));
        craft()->urlManager->setRouteVariables([
            'product' => $product
        ]);
    }

    /**
     * @return Commerce_ProductModel
     * @throws Exception
     */
    private function _setProductFromPost()
    {
        $productId = craft()->request->getPost('productId');
        $locale = craft()->request->getPost('locale');

        if ($productId) {
            $product = craft()->commerce_products->getById($productId, $locale);

            if (!$product) {
                throw new Exception(Craft::t('No product with the ID “{id}”',
                    ['id' => $productId]));
            }
        } else {
            $product = new Commerce_ProductModel();
        }

        $availableOn = craft()->request->getPost('availableOn');
        $expiresOn = craft()->request->getPost('expiresOn');

        $product->availableOn = $availableOn ? DateTime::createFromString($availableOn, craft()->timezone) : $product->availableOn;
        $product->expiresOn = $expiresOn ? DateTime::createFromString($expiresOn, craft()->timezone) : null;
        $product->typeId = craft()->request->getPost('typeId');
        $product->enabled = craft()->request->getPost('enabled');
        $product->promotable = craft()->request->getPost('promotable');
        $product->freeShipping = craft()->request->getPost('freeShipping');
        $product->authorId = craft()->userSession->id;
        $product->taxCategoryId = craft()->request->getPost('taxCategoryId', $product->taxCategoryId);
        $product->localeEnabled = (bool)craft()->request->getPost('localeEnabled', $product->localeEnabled);

        if (!$product->availableOn) {
            $product->availableOn = new DateTime();
        }

        $product->getContent()->title = craft()->request->getPost('title', $product->title);
        $product->slug = craft()->request->getPost('slug', $product->slug);
        $product->setContentFromPost('fields');

        return $product;
    }

    /**
     * @param Commerce_ProductModel $product
     *
     * @return Commerce_VariantModel
     */
    private function _setVariantsFromPost(Commerce_ProductModel $product)
    {
        $variantsPost = craft()->request->getPost('variants');
        $variants = [];
        $count = 1;
        foreach ($variantsPost as $key => $variant) {
            if (strncmp($key, 'new', 3) !== 0) {
                $variantModel = craft()->commerce_variants->getById($key);
            }else{
                $variantModel = new Commerce_VariantModel();
            }

            $variantModel->setAttributes($variant);
            if(isset($variant['fields'])){
                $variantModel->setContentFromPost($variant['fields']);
            }
            $variantModel->sortOrder = $count++;
            $variantModel->setProduct($product);
            $variants[] = $variantModel;
        }

        $product->setVariants($variants);

        return $variants;
    }
}
