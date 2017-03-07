<?php
namespace Craft;

/**
 * Class Commerce_SalesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_SalesController extends Commerce_BaseCpController
{

    /**
     * @throws HttpException
     */
    public function init()
    {
        craft()->userSession->requirePermission('commerce-managePromotions');
        parent::init();
    }

    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $sales = craft()->commerce_sales->getAllSales();
        $this->renderTemplate('commerce/promotions/sales/index', compact('sales'));
    }

    /**
     * Create/Edit Sale
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['sale'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['sale'] = craft()->commerce_sales->getSaleById($id);

                if (!$variables['sale']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['sale'] = new Commerce_SaleModel();
            }
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['sale']->name;
        } else {
            $variables['title'] = Craft::t('Create a new sale');
        }

        //getting user groups map
        if (craft()->getEdition() == Craft::Pro) {
            $groups = craft()->userGroups->getAllGroups();
            $variables['groups'] = \CHtml::listData($groups, 'id', 'name');
        } else {
            $variables['groups'] = [];
        }

        //getting product types maps
        $types = craft()->commerce_productTypes->getAllProductTypes();
        $variables['types'] = \CHtml::listData($types, 'id', 'name');


        $variables['products'] = null;
        $products = $productIds = [];
        if (empty($variables['id'])) {
            $productIds = explode('|', craft()->request->getParam('productIds'));
        } else {
            $productIds = $variables['sale']->getProductIds();
        }
        foreach ($productIds as $productId) {
            $product = craft()->commerce_products->getProductById($productId);
            if($product){
                $products[] = $product;
            }
        }
        $variables['products'] = $products;

        $this->renderTemplate('commerce/promotions/sales/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $sale = new Commerce_SaleModel();

        // Shared attributes
        $fields = [
            'id',
            'name',
            'description',
            'discountType',
            'enabled'
        ];
        foreach ($fields as $field) {
            $sale->$field = craft()->request->getPost($field);
        }

        $dateFields = [
            'dateFrom',
            'dateTo'
        ];
        foreach ($dateFields as $field) {
            $sale->$field = (($date = craft()->request->getPost($field)) ? DateTime::createFromString($date, craft()->timezone) : null);
        }

        $discountAmount = craft()->request->getPost('discountAmount');
        if ($sale->discountType == 'percent') {
            $localeData = craft()->i18n->getLocaleData();
            $percentSign = $localeData->getNumberSymbol('percentSign');
            if (strpos($discountAmount, $percentSign) or (float)$discountAmount >= 1) {
                $sale->discountAmount = (float) $discountAmount / -100;
            } else {
                $sale->discountAmount = (float) $discountAmount * -1;
            };
        } else {
            $sale->discountAmount = (float) $discountAmount * -1;
        }

        $products = craft()->request->getPost('products', []);
        if (!$products) {
            $products = [];
        }
        $products = array_unique($products);

        $productTypes = craft()->request->getPost('productTypes', []);
        if (!$productTypes) {
            $productTypes = [];
        }
        $productTypes = array_unique($productTypes);

        $groups = craft()->request->getPost('groups', []);
        if (!$groups) {
            $groups = [];
        }
        $groups = array_unique($groups);

        // Save it
        if (craft()->commerce_sales->saveSale($sale, $groups, $productTypes, $products)) {
            craft()->userSession->setNotice(Craft::t('Sale saved.'));
            $this->redirectToPostedUrl($sale);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save sale.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['sale' => $sale]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->commerce_sales->deleteSaleById($id);
        $this->returnJson(['success' => true]);
    }

}
