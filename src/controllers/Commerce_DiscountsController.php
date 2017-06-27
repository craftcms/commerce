<?php
namespace Craft;

/**
 * Class Commerce_DiscountsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_DiscountsController extends Commerce_BaseCpController
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
        $discounts = craft()->commerce_discounts->getAllDiscounts(['order' => 'sortOrder']);
        $this->renderTemplate('commerce/promotions/discounts/index',
            compact('discounts'));
    }

    /**
     * Create/Edit Discount
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['discount'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['discount'] = craft()->commerce_discounts->getDiscountById($id);

                if (!$variables['discount']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['discount'] = new Commerce_DiscountModel();
            }
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['discount']->name;
        } else {
            $variables['title'] = Craft::t('Create a Discount');
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
            $productIds = $variables['discount']->getProductIds();
        }
        foreach ($productIds as $productId) {
            $product = craft()->commerce_products->getProductById($productId);
            if($product){
                $products[] = $product;
            }
        }
        $variables['products'] = $products;

        $this->renderTemplate('commerce/promotions/discounts/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $discount = new Commerce_DiscountModel();

        // Shared attributes
        $fields = [
            'id',
            'name',
            'description',
            'enabled',
            'stopProcessing',
            'sortOrder',
            'purchaseTotal',
            'purchaseQty',
            'maxPurchaseQty',
            'freeShipping',
            'excludeOnSale',
            'code',
            'perUserLimit',
            'perEmailLimit',
            'percentageOffSubject',
            'totalUseLimit'
        ];
        foreach ($fields as $field) {
            $discount->$field = craft()->request->getPost($field);
        }

        $discountAmountsFields = [
            'baseDiscount',
            'perItemDiscount'
        ];
        foreach ($discountAmountsFields as $field) {
            $discount->$field = (float) craft()->request->getPost($field) * -1;
        }

        $dateFields = [
            'dateFrom',
            'dateTo'
        ];
        foreach ($dateFields as $field) {
            $discount->$field = (($date = craft()->request->getPost($field)) ? DateTime::createFromString($date, craft()->timezone) : null);
        }

        // Format into a %
        $percentDiscountAmount = craft()->request->getPost('percentDiscount');
        $localeData = craft()->i18n->getLocaleData();
        $percentSign = $localeData->getNumberSymbol('percentSign');
        if (strpos($percentDiscountAmount, $percentSign) or (float) $percentDiscountAmount >= 1) {
            $discount->percentDiscount = (float) $percentDiscountAmount / -100;
        } else {
            $discount->percentDiscount = (float) $percentDiscountAmount * -1;
        }

        $products = craft()->request->getPost('products', []);
        if (!$products) {
            $products = [];
        }

        $productTypes = craft()->request->getPost('productTypes', []);
        if (!$productTypes) {
            $productTypes = [];
        }

        $groups = craft()->request->getPost('groups', []);
        if (!$groups) {
            $groups = [];
        }

        // Save it
        if (craft()->commerce_discounts->saveDiscount($discount, $groups, $productTypes,
            $products)
        ) {
            craft()->userSession->setNotice(Craft::t('Discount saved.'));
            $this->redirectToPostedUrl($discount);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save discount.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['discount' => $discount]);
    }

    /**
     * @return \HttpResponse
     * @throws HttpException
     */
    public function actionReorder()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $ids = JsonHelper::decode(craft()->request->getRequiredPost('ids'));
        if ($success = craft()->commerce_discounts->reorderDiscounts($ids))
        {
            return $this->returnJson(['success' => $success]);
        };

        return $this->returnJson(['error' => Craft::t("Couldn’t reorder discounts.")]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->commerce_discounts->deleteDiscountById($id);
        $this->returnJson(['success' => true]);
    }

    /**
     * @throws HttpException
     */
    public function actionClearCouponUsageHistory()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->commerce_discounts->clearCouponUsageHistory($id);

        $this->returnJson(['success' => true]);
    }

}
