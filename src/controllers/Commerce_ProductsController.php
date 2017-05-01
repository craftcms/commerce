<?php
namespace Craft;

use Commerce\Helpers\CommerceProductHelper;
use Commerce\Helpers\CommerceVariantMatrixHelper as VariantMatrixHelper;

/**
 * Class Commerce_ProductsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ProductsController extends Commerce_BaseCpController
{

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = ['actionViewSharedProduct'];

    /**
     * @throws HttpException
     */
    public function init()
    {
        craft()->userSession->requirePermission('commerce-manageProducts');
        parent::init();
    }


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

        if (!empty($variables['product']->id))
        {
            $variables['title'] = $variables['product']->title;
        }
        else
        {
            $variables['title'] = Craft::t('Create a new product');
        }

        $variables['continueEditingUrl'] = "commerce/products/".$variables['productTypeHandle']."/{id}-{slug}".
            (craft()->isLocalized() && craft()->getLanguage() != $variables['localeId'] ? '/'.$variables['localeId'] : '');

        $this->_prepVariables($variables);

        if ($variables['product']->getType()->hasVariants)
        {
            $variables['variantMatrixHtml'] = VariantMatrixHelper::getVariantMatrixHtml($variables['product']);
        }
        else
        {
            craft()->templates->includeJs('Craft.Commerce.initUnlimitedStockCheckbox($("#meta-pane"));');
        }

        // Enable Live Preview?
        if (!craft()->request->isMobileBrowser(true) && craft()->commerce_productTypes->isProductTypeTemplateValid($variables['productType']))
        {
            craft()->templates->includeJs('Craft.LivePreview.init('.JsonHelper::encode([
                    'fields'        => '#title-field, #fields > div > div > .field',
                    'extraFields'   => '#meta-pane, #variants-pane',
                    'previewUrl'    => $variables['product']->getUrl(),
                    'previewAction' => 'commerce/products/previewProduct',
                    'previewParams' => [
                        'typeId'    => $variables['productType']->id,
                        'productId' => $variables['product']->id,
                        'locale'    => $variables['product']->locale,
                    ]
                ]).');');

            $variables['showPreviewBtn'] = true;

            // Should we show the Share button too?
            if ($variables['product']->id)
            {
                // If the product is enabled, use its main URL as its share URL.
                if ($variables['product']->getStatus() == Commerce_ProductModel::LIVE)
                {
                    $variables['shareUrl'] = $variables['product']->getUrl();
                }
                else
                {
                    $variables['shareUrl'] = UrlHelper::getActionUrl('commerce/products/shareProduct', [
                        'productId' => $variables['product']->id,
                        'locale'    => $variables['product']->locale
                    ]);
                }
            }
        }
        else
        {
            $variables['showPreviewBtn'] = false;
        }

        $variables['promotions']['sales'] = craft()->commerce_sales->getSalesForProduct($variables['product']);

        craft()->templates->includeCssResource('commerce/product.css');
        $this->renderTemplate('commerce/products/_edit', $variables);
    }

    private function _prepProductVariables(&$variables)
    {
        $variables['localeIds'] = craft()->i18n->getEditableLocaleIds();

        if (!$variables['localeIds'])
        {
            throw new HttpException(403, Craft::t('Your account doesn’t have permission to edit any of this site’s locales.'));
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

        if (!empty($variables['productTypeHandle']))
        {
            $variables['productType'] = craft()->commerce_productTypes->getProductTypeByHandle($variables['productTypeHandle']);
        }

        if (empty($variables['productType']))
        {
            throw new HttpException(400,
                craft::t('Wrong product type specified'));
        }

        if (empty($variables['product']))
        {
            if (!empty($variables['productId']))
            {
                $variables['product'] = craft()->commerce_products->getProductById($variables['productId'], $variables['localeId']);

                if (!$variables['product'])
                {
                    throw new HttpException(404);
                }
            }
            else
            {
                $variables['product'] = new Commerce_ProductModel();
                $variables['product']->typeId = $variables['productType']->id;
                $taxCategories = $variables['productType']->getTaxCategories();
                $variables['product']->taxCategoryId = key($taxCategories);
                $shippingCategories = $variables['productType']->getShippingCategories();
                $variables['product']->shippingCategoryId = key($shippingCategories);
                $variables['product']->typeId = $variables['productType']->id;
                if ($variables['localeId'])
                {
                    $variables['product']->locale = $variables['localeId'];
                }
            }
        }

        if (!empty($variables['product']->id))
        {
            $this->enforceProductPermissions($variables['product']);
            $variables['enabledLocales'] = craft()->elements->getEnabledLocalesForElement($variables['product']->id);
        }
        else
        {
            $variables['enabledLocales'] = [];

            foreach (craft()->i18n->getEditableLocaleIds() as $locale)
            {
                $variables['enabledLocales'][] = $locale;
            }
        }

        //raising event
        $event = new Event($this, [
            'product' => $variables['product']
        ]);

        craft()->commerce_products->onBeforeEditProduct($event);
    }

    /**
     * @param $variables
     *
     * @throws HttpException
     */
    private function _prepVariables(&$variables)
    {
        $variables['tabs'] = [];

        foreach ($variables['productType']->getFieldLayout()->getTabs() as $index => $tab)
        {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;
            if ($variables['product']->hasErrors())
            {
                foreach ($tab->getFields() as $field)
                {
                    if ($variables['product']->getErrors($field->getField()->handle))
                    {
                        $hasErrors = true;
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Craft::t($tab->name),
                'url'   => '#tab'.($index + 1),
                'class' => ($hasErrors ? 'error' : null)
            ];
        }

        $variables['primaryVariant'] = ArrayHelper::getFirstValue($variables['product']->getVariants());
    }

    /**
     * Previews a product.
     *
     * @throws HttpException
     * @return null
     */
    public function actionPreviewProduct()
    {
        $this->requirePostRequest();

        $product = $this->_setProductFromPost();

        $this->enforceProductPermissions($product);

        $this->_showProduct($product);
    }

    /**
     * Redirects the client to a URL for viewing a disabled product on the front end.
     *
     * @param mixed $productId
     * @param mixed $locale
     *
     * @throws HttpException
     * @return null
     */
    public function actionShareProduct($productId, $locale = null)
    {
        $product = craft()->commerce_products->getProductById($productId, $locale);

        if (!$product)
        {
            throw new HttpException(404);
        }

        $this->enforceProductPermissions($product);

        // Make sure the product actually can be viewed
        if (!craft()->commerce_productTypes->isProductTypeTemplateValid($product->getType()))
        {
            throw new HttpException(404);
        }

        // Create the token and redirect to the product URL with the token in place
        $token = craft()->tokens->createToken([
            'action' => 'commerce/products/viewSharedProduct',
            'params' => ['productId' => $productId, 'locale' => $product->locale]
        ]);

        $url = UrlHelper::getUrlWithToken($product->getUrl(), $token);
        craft()->request->redirect($url);
    }

    /**
     * Shows an product/draft/version based on a token.
     *
     * @param mixed $productId
     * @param mixed $locale
     *
     * @throws HttpException
     * @return null
     */
    public function actionViewSharedProduct($productId, $locale = null)
    {
        $this->requireToken();

        $product = craft()->commerce_products->getProductById($productId, $locale);

        if (!$product)
        {
            throw new HttpException(404);
        }

        $this->_showProduct($product);
    }

    /**
     * Displays a product.
     *
     * @param Commerce_ProductModel $product
     *
     * @throws HttpException
     * @return null
     */
    private function _showProduct(Commerce_ProductModel $product)
    {
        $productType = $product->getType();

        if (!$productType)
        {
            Craft::log('Attempting to preview a product that doesn’t have a type', LogLevel::Error);
            throw new HttpException(404);
        }

        craft()->setLanguage($product->locale);

        // Have this product override any freshly queried products with the same ID/locale
        craft()->elements->setPlaceholderElement($product);

        craft()->templates->getTwig()->disableStrictVariables();

        $this->renderTemplate($productType->template, [
            'product' => $product
        ]);
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
        $product = craft()->commerce_products->getProductById($productId);

        if (!$product)
        {
            throw new Exception(Craft::t('No product exists with the ID “{id}”.',
                ['id' => $productId]));
        }

        $this->enforceProductPermissions($product);

        if (craft()->commerce_products->deleteProduct($product))
        {
            if (craft()->request->isAjaxRequest())
            {
                $this->returnJson(['success' => true]);
            }
            else
            {
                craft()->userSession->setNotice(Craft::t('Product deleted.'));
                $this->redirectToPostedUrl($product);
            }
        }
        else
        {
            if (craft()->request->isAjaxRequest())
            {
                $this->returnJson(['success' => false]);
            }
            else
            {
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
        CommerceProductHelper::populateProductVariantModels($product, craft()->request->getPost('variants'));

        $this->enforceProductPermissions($product);


        $existingProduct = (bool)$product->id;

        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

        if (craft()->commerce_products->saveProduct($product))
        {

            if ($transaction !== null)
            {
                $transaction->commit();
            }

            craft()->userSession->setNotice(Craft::t('Product saved.'));

            $this->redirectToPostedUrl($product);
        }

        if ($transaction !== null)
        {
            $transaction->rollback();
        }
        // Since Product may have been ok to save and an ID assigned,
        // but child model validation failed and the transaction rolled back.
        // Since action failed, lets remove the ID that was no persisted.
        if (!$existingProduct)
        {
            $product->id = null;
        }


        craft()->userSession->setError(Craft::t('Couldn’t save product.'));
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

        if ($productId)
        {
            $product = craft()->commerce_products->getProductById($productId, $locale);

            if (!$product)
            {
                throw new Exception(Craft::t('No product with the ID “{id}”',
                    ['id' => $productId]));
            }
        }
        else
        {
            $product = new Commerce_ProductModel();
        }

        CommerceProductHelper::populateProductModel($product, craft()->request->getPost());

        $product->localeEnabled = (bool)craft()->request->getPost('localeEnabled', $product->localeEnabled);
        $product->getContent()->title = craft()->request->getPost('title', $product->title);
        $product->setContentFromPost('fields');

        return $product;
    }

    /**
     * @param Commerce_ProductModel $product
     *
     * @throws HttpException
     */
    protected function enforceProductPermissions(Commerce_ProductModel $product)
    {

        if (!$product->getType())
        {
            Craft::log('Attempting to access a product that doesn’t have a type', LogLevel::Error);
            throw new HttpException(404);
        }

        craft()->userSession->requirePermission('commerce-manageProductType:'.$product->getType()->id);
    }
}
