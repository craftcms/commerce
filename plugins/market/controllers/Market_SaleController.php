<?php
namespace Craft;

/**
 * Class Market_SaleController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_SaleController extends Market_BaseController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $this->requireAdmin();

        $sales = craft()->market_sale->getAll(['order' => 'name']);
        $this->renderTemplate('market/promotions/sales/index',
            compact('sales'));
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
        $this->requireAdmin();

        if (empty($variables['sale'])) {
            if (!empty($variables['id'])) {
                $id                = $variables['id'];
                $variables['sale'] = craft()->market_sale->getById($id);

                if (!$variables['sale']->id) {
                    throw new HttpException(404);
                }
            } else {
                $variables['sale'] = new Market_SaleModel();
            }
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['sale']->name;
        } else {
            $variables['title'] = Craft::t('Create a Sale');
        }

        //getting user groups map
        $groups              = craft()->userGroups->getAllGroups();
        $variables['groups'] = \CHtml::listData($groups, 'id', 'name');

        //getting product types maps
        $types              = craft()->market_productType->getAll();
        $variables['types'] = \CHtml::listData($types, 'id', 'name');



        $variables['products'] = null;
        $products = $productIds = [];
        if (empty($variables['id']))
        {
            $productIds = explode('|', craft()->request->getParam('productIds'));
        }else{
            $productIds = $variables['sale']->getProductsIds();
        }
        foreach ($productIds as $productId)
        {
            $products[] = craft()->market_product->getById($productId);
        }
        $variables['products'] = $products;

        $this->renderTemplate('market/promotions/sales/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $sale = new Market_SaleModel();

        // Shared attributes
        $fields = [
            'id',
            'name',
            'description',
            'dateFrom',
            'dateTo',
            'discountType',
            'discountAmount',
            'enabled'
        ];
        foreach ($fields as $field) {
            $sale->$field = craft()->request->getPost($field);
        }

        $products     = craft()->request->getPost('products');
        if(!$products){
            $products = [];
        }
        $productTypes = craft()->request->getPost('productTypes', []);
        $groups       = craft()->request->getPost('groups', []);

        // Save it
        if (craft()->market_sale->save($sale, $groups, $productTypes,
            $products)
        ) {
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
        $this->requireAdmin();

        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->market_sale->deleteById($id);
        $this->returnJson(['success' => true]);
    }

}