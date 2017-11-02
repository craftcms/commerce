<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\i18n\Locale;
use yii\web\HttpException;
use yii\web\Response;

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
    // Public Methods
    // =========================================================================

    /**
     * @throws HttpException
     */
    public function init()
    {
        $this->requirePermission('commerce-managePromotions');
        parent::init();
    }

    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $sales = Plugin::getInstance()->getSales()->getAllSales();
        return $this->renderTemplate('commerce/promotions/sales/index', compact('sales'));
    }

    /**
     * @param int|null  $id
     * @param Sale|null $sale
     *
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, Sale $sale = null): Response
    {
        $variables = [
            'id' => $id,
            'sale' => $sale
        ];

        $variables['productElementType'] = Product::class;

        if (!$variables['sale']) {
            if ($variables['id']) {
                $variables['sale'] = Plugin::getInstance()->getSales()->getSaleById($variables['id']);

                if (!$variables['sale']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['sale'] = new Sale();
            }
        }

        if ($variables['sale']->id) {
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
            $product = Plugin::getInstance()->getProducts()->getProductById((int)$productId);
            if ($product) {
                $products[] = $product;
            }
        }
        $variables['products'] = $products;

        return $this->renderTemplate('commerce/promotions/sales/_edit', $variables);
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
        $request = Craft::$app->getRequest();

        foreach ($fields as $field) {
            $sale->$field = $request->getParam($field);
        }

        $dateFields = [
            'dateFrom',
            'dateTo'
        ];
        foreach ($dateFields as $field) {
            if (($date = $request->getParam($field)) !== false) {
                $sale->$field = DateTimeHelper::toDateTime($date) ?: null;
            } else {
                $sale->$field = $sale->$date;
            }
        }

        $discountAmount = $request->getParam('discountAmount');

        if ($sale->discountType === 'percent') {
            $localeData = Craft::$app->getLocale();
            $percentSign = $localeData->getNumberSymbol(Locale::SYMBOL_PERCENT);

            if (strpos($discountAmount, $percentSign) or (float)$discountAmount >= 1) {
                $sale->discountAmount = (float)$discountAmount / -100;
            } else {
                $sale->discountAmount = (float)$discountAmount * -1;
            }
        } else {
            $sale->discountAmount = (float)$discountAmount * -1;
        }

        $products = $request->getParam('products', []);

        if (!$products) {
            $products = [];
        }

        $products = array_unique($products);

        $productTypes = $request->getParam('productTypes', []);

        if (!$productTypes) {
            $productTypes = [];
        }

        $productTypes = array_unique($productTypes);

        $groups = $request->getParam('groups', []);

        if (!$groups) {
            $groups = [];
        }

        $groups = array_unique($groups);

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
        return $this->asJson(['success' => true]);
    }
}
