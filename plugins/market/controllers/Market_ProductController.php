<?php
namespace Craft;

/**
 * Class Market_ProductController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
use Market\Helpers\MarketDbHelper;

/**
 * Class Market_ProductController
 *
 * @package Craft
 */
class Market_ProductController extends Market_BaseController
{
    /** @var bool All product changes should be by a logged in user */
    protected $allowAnonymous = false;

    /**
     * Index of products
     */
    public function actionProductIndex()
    {
        $this->requireAdmin();

        $variables['productTypes'] = craft()->market_productType->getAll();
        $variables['taxCategories'] = craft()->market_taxCategory->getAll();
        $this->renderTemplate('market/products/_index', $variables);
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
        $this->requireAdmin();

        $this->_prepProductVariables($variables);

        if (!empty($variables['product']->id)) {
            $variables['title'] = $variables['product']->title;
        } else {
            $variables['title'] = Craft::t('Create a new Product');
        }

        $variables['continueEditingUrl'] = "market/products/" . $variables['productTypeHandle'] . "/{id}-{slug}".
            (craft()->isLocalized() && craft()->getLanguage() != $variables['localeId'] ? '/'.$variables['localeId'] : '');

        $variables['taxCategories'] = \CHtml::listData(craft()->market_taxCategory->getAll(),
            'id', 'name');

        $this->_prepVariables($variables);
        craft()->templates->includeCssResource('market/product.css');
        $this->renderTemplate('market/products/_edit', $variables);
    }


    /**
     * @param $variables
     *
     * @throws HttpException
     */
    private function _prepVariables(&$variables)
    {
        $variables['tabs'] = [];

        if (empty($variables['masterVariant'])) {
            $variables['masterVariant'] = $variables['product']->masterVariant ?: new Market_VariantModel;
        }

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

    }

    /**
     * Deletes a product.
     *
     * @throws Exception if you try to edit a non existing Id.
     */
    public function actionDeleteProduct()
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $productId = craft()->request->getRequiredPost('productId');
        $product = craft()->market_product->getById($productId);

        if (!$product->id) {
            throw new Exception(Craft::t('No product exists with the ID “{id}”.',
                ['id' => $productId]));
        }

        if (craft()->market_product->delete($product)) {
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
        $this->requireAdmin();
        $this->requirePostRequest();

        $product = $this->_setProductFromPost();
        $masterVariant = $this->_setMasterVariantFromPost($product);

        $existingProduct = (bool)$product->id;

        MarketDbHelper::beginStackedTransaction();

        if (craft()->market_product->save($product)) {
            $masterVariant->productId = $product->id;

            if (craft()->market_variant->save($masterVariant)) {

                MarketDbHelper::commitStackedTransaction();

                craft()->userSession->setNotice(Craft::t('Product saved.'));

                if (craft()->request->getPost('redirectToVariant')) {
                    $this->redirect($product->getCpEditUrl() . '/variants/new');
                } else {
                    $this->redirectToPostedUrl($product);
                }
            }
        }

        MarketDbHelper::rollbackStackedTransaction();
        // Since Product may have been ok to save and an ID assigned,
        // but child model validation failed and the transaction rolled back.
        // Since action failed, lets remove the ID that was no persisted.
        if (!$existingProduct) {
            $product->id = null;
        }


        craft()->userSession->setNotice(Craft::t("Couldn't save product."));
        craft()->urlManager->setRouteVariables([
            'product' => $product,
            'masterVariant' => $masterVariant
        ]);
    }

    /**
     * @return Market_ProductModel
     * @throws Exception
     */
    private function _setProductFromPost()
    {
        $productId = craft()->request->getPost('productId');
        $locale = craft()->request->getPost('locale');

        if ($productId) {
            $product = craft()->market_product->getById($productId,$locale);

            if (!$product) {
                throw new Exception(Craft::t('No product with the ID “{id}”',
                    ['id' => $productId]));
            }
        } else {
            $product = new Market_ProductModel();
        }

        $availableOn = craft()->request->getPost('availableOn');
        $expiresOn = craft()->request->getPost('expiresOn');

        $product->availableOn = $availableOn ? DateTime::createFromString($availableOn, craft()->timezone) : $product->availableOn;
        $product->expiresOn = $expiresOn ? DateTime::createFromString($expiresOn, craft()->timezone) : null;
        $product->typeId = craft()->request->getPost('typeId');
        $product->enabled = craft()->request->getPost('enabled');
        $product->promotable    = craft()->request->getPost('promotable');
        $product->freeShipping  = craft()->request->getPost('freeShipping');
        $product->authorId = craft()->userSession->id;
        $product->taxCategoryId = craft()->request->getPost('taxCategoryId', $product->taxCategoryId);
        $product->localeEnabled = (bool) craft()->request->getPost('localeEnabled', $product->localeEnabled);

        if (!$product->availableOn) {
            $product->availableOn = new DateTime();
        }

        $product->getContent()->title = craft()->request->getPost('title', $product->title);
        $product->slug = craft()->request->getPost('slug', $product->slug);
        $product->setContentFromPost('fields');

        return $product;
    }


    /**
     * @param Market_ProductModel $product
     *
     * @return Market_VariantModel
     */
    private function _setMasterVariantFromPost(Market_ProductModel $product)
    {
        $attributes = craft()->request->getPost('masterVariant');
        $masterVariant = $product->masterVariant ?: new Market_VariantModel;
        $masterVariant->setAttributes($attributes);
        $masterVariant->isMaster = true;

        return $masterVariant;
    }

    private function _prepProductVariables(&$variables)
    {
        if (craft()->isLocalized()){
            // default to all use all locales for now
            $variables['localeIds'] = craft()->i18n->getEditableLocaleIds();

        }else{
            $variables['localeIds'] = array(craft()->i18n->getPrimarySiteLocaleId());
        }

        if (empty($variables['localeId']))
        {
            $variables['localeId'] = craft()->language;

            if (!in_array($variables['localeId'], $variables['localeIds']))
            {
                $variables['localeId'] = $variables['localeIds'][0];
            }
        }
        else
        {
            // Make sure they were requesting a valid locale
            if (!in_array($variables['localeId'], $variables['localeIds']))
            {
                throw new HttpException(404);
            }
        }

        if (!empty($variables['productTypeHandle'])) {
            $variables['productType'] = craft()->market_productType->getByHandle($variables['productTypeHandle']);
        }

        if (empty($variables['productType'])) {
            throw new HttpException(400,
                craft::t('Wrong product type specified'));
        }

        if (empty($variables['product'])) {
            if (!empty($variables['productId'])) {
                $variables['product'] = craft()->market_product->getById($variables['productId'],$variables['localeId']);

                if (!$variables['product']->id) {
                    throw new HttpException(404);
                }
            } else {
                $variables['product'] = new Market_ProductModel();
                $variables['product']->typeId = $variables['productType']->id;
                if ($variables['localeId'])
                {
                    $variables['product']->locale = $variables['localeId'];
                }

            }
        }

        if (!empty($variables['product']->id))
        {
            $variables['enabledLocales'] = craft()->elements->getEnabledLocalesForElement($variables['product']->id);
        }else{
            $variables['enabledLocales'] = array();

            foreach (craft()->i18n->getEditableLocaleIds() as $locale)
            {
                $variables['enabledLocales'][] = $locale;
            }
        }
    }
} 
