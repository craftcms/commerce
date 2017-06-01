<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;

/**
 * Class Sales Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class SalesController extends BaseCpController
{

    /**
     * @throws HttpException
     */
    public function init()
    {
        $this->requirePermission('commerce-managePromotions');
        parent::init();
    }

    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $sales = Plugin::getInstance()->getSales()->getAllSales();
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
        $variables['productElementType'] = Product::class;

        if (empty($variables['sale'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['sale'] = Plugin::getInstance()->getSales()->getSaleById($id);

                if (!$variables['sale']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['sale'] = new Sale();
            }
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['sale']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new sale');
        }

        //getting user groups map
        if (Craft::$app->getEdition() == Craft::Pro) {
            $groups = Craft::$app->getUserGroups()->getAllGroups();
            $variables['groups'] = ArrayHelper::map($groups, 'id', 'name');
        } else {
            $variables['groups'] = [];
        }

        //getting product types maps
        $types = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        $variables['types'] = ArrayHelper::map($types, 'id', 'name');


        $variables['products'] = null;
        $products = $productIds = [];
        if (empty($variables['id'])) {
            $productIds = explode('|', Craft::$app->getRequest()->getParam('productIds'));
        } else {
            $productIds = $variables['sale']->getProductIds();
        }
        foreach ($productIds as $productId) {
            $product = Plugin::getInstance()->getProducts()->getProductById((int) $productId);
            if ($product) {
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

        $sale = new Sale();

        // Shared attributes
        $fields = [
            'id',
            'name',
            'description',
            'discountType',
            'enabled'
        ];
        foreach ($fields as $field) {
            $sale->$field = Craft::$app->getRequest()->getParam($field);
        }

        $dateFields = [
            'dateFrom',
            'dateTo'
        ];
        foreach ($dateFields as $field) {
            $sale->$field = (($date = Craft::$app->getRequest()->getParam($field)) ? DateTime::createFromString($date, Craft::$app->getTimeZone()) : null);
        }

        $discountAmount = Craft::$app->getRequest()->getParam('discountAmount');
        if ($sale->discountType == 'percent') {
            $localeData = Craft::$app->getI18n()->getLocaleData();
            $percentSign = $localeData->getNumberSymbol('percentSign');
            if (strpos($discountAmount, $percentSign) or floatval($discountAmount) >= 1) {
                $sale->discountAmount = floatval($discountAmount) / -100;
            } else {
                $sale->discountAmount = floatval($discountAmount) * -1;
            };
        } else {
            $sale->discountAmount = floatval($discountAmount) * -1;
        }

        $products = Craft::$app->getRequest()->getParam('products', []);
        if (!$products) {
            $products = [];
        }

        $productTypes = Craft::$app->getRequest()->getParam('productTypes', []);
        if (!$productTypes) {
            $productTypes = [];
        }

        $groups = Craft::$app->getRequest()->getParam('groups', []);
        if (!$groups) {
            $groups = [];
        }

        // Save it
        if (Plugin::getInstance()->getSales()->saveSale($sale, $groups, $productTypes, $products)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Sale saved.'));
            $this->redirectToPostedUrl($sale);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save sale.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['sale' => $sale]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');

        Plugin::getInstance()->getSales()->deleteSaleById($id);
        $this->asJson(['success' => true]);
    }

}
