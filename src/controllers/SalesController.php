<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Product;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Sale as SaleRecord;
use craft\elements\Category;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\i18n\Locale;
use Exception;
use Throwable;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;
use function explode;
use function get_class;

/**
 * Class Sales Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SalesController extends BaseCpController
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->requirePermission('commerce-managePromotions');
    }

    /**
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionIndex(): Response
    {
        $sales = Plugin::getInstance()->getSales()->getAllSales();
        return $this->renderTemplate('commerce/promotions/sales/index', compact('sales'));
    }

    /**
     * @param int|null $id
     * @param Sale|null $sale
     * @return Response
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionEdit(int $id = null, Sale $sale = null): Response
    {
        $variables = compact('id', 'sale');
        $isNewSale = false;

        if (!$variables['sale']) {
            if ($variables['id']) {
                $variables['sale'] = Plugin::getInstance()->getSales()->getSaleById($variables['id']);

                if (!$variables['sale']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['sale'] = new Sale();
                $isNewSale = true;
            }
        }

        $tabName = sprintf('Sale (%s)', $isNewSale ? 'New' : 'ID: ' . $variables['sale']->id);
        DebugPanel::addModelTab($tabName, $variables['sale']);

        $this->_populateVariables($variables);

        return $this->renderTemplate('commerce/promotions/sales/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws \yii\base\Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $sale = new Sale();

        // Shared attributes
        $request = Craft::$app->getRequest();
        $sale->id = $request->getBodyParam('id');
        $sale->name = $request->getBodyParam('name');
        $sale->description = $request->getBodyParam('description');
        $sale->apply = $request->getBodyParam('apply');
        $sale->enabled = (bool)$request->getBodyParam('enabled');

        $dateFields = [
            'dateFrom',
            'dateTo',
        ];
        foreach ($dateFields as $field) {
            if (($date = $request->getBodyParam($field)) !== false) {
                $sale->$field = DateTimeHelper::toDateTime($date) ?: null;
            } else {
                $sale->$field = $sale->$date;
            }
        }

        $applyAmount = $request->getBodyParam('applyAmount');
        $sale->sortOrder = $request->getBodyParam('sortOrder');
        $sale->ignorePrevious = (bool)$request->getBodyParam('ignorePrevious');
        $sale->stopProcessing = (bool)$request->getBodyParam('stopProcessing');
        $sale->categoryRelationshipType = $request->getBodyParam('categoryRelationshipType');

        $applyAmount = Localization::normalizeNumber($applyAmount);
        if ($sale->apply == SaleRecord::APPLY_BY_PERCENT || $sale->apply == SaleRecord::APPLY_TO_PERCENT) {
            if ((float)$applyAmount >= 1) {
                $sale->applyAmount = (float)$applyAmount / -100;
            } else {
                $sale->applyAmount = -(float)$applyAmount;
            }
        } else {
            $sale->applyAmount = (float)$applyAmount * -1;
        }

        $purchasables = [];
        $purchasableGroups = $request->getBodyParam('purchasables') ?: [];
        foreach ($purchasableGroups as $group) {
            if (is_array($group)) {
                array_push($purchasables, ...$group);
            }
        }
        $sale->setPurchasableIds(array_unique($purchasables));

        $categories = $request->getBodyParam('categories', []);

        if (!$categories) {
            $categories = [];
        }

        $sale->setCategoryIds(array_unique($categories));

        $groups = $request->getBodyParam('groups', []);

        if (!$groups) {
            $groups = [];
        }

        $sale->setUserGroupIds(array_unique($groups));

        // Save it
        if (Plugin::getInstance()->getSales()->saveSale($sale)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Sale saved.'));
            return $this->redirectToPostedUrl($sale);
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save sale.'));
        }

        $variables = [
            'sale' => $sale,
        ];
        $this->_populateVariables($variables);

        Craft::$app->getUrlManager()->setRouteParams($variables);
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        if ($success = Plugin::getInstance()->getSales()->reorderSales($ids)) {
            return $this->asJson(['success' => $success]);
        }

        return $this->asJson(['error' => Craft::t('commerce', 'Couldn’t reorder sales.')]);
    }

    /**
     * @return Response
     * @throws Exception
     * @throws Throwable
     * @throws StaleObjectException
     * @throws BadRequestHttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->getRequest()->getBodyParam('id');
        $ids = Craft::$app->getRequest()->getBodyParam('ids');

        if ((!$id && empty($ids)) || ($id && !empty($ids))) {
            throw new BadRequestHttpException('id or ids must be specified.');
        }

        if ($id) {
            $this->requireAcceptsJson();
            $ids = [$id];
        }

        foreach ($ids as $id) {
            Plugin::getInstance()->getSales()->deleteSaleById($id);
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Sales deleted.'));

        return $this->redirect($this->request->getReferrer());
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionGetAllSales(): Response
    {
        $this->requireAcceptsJson();
        $sales = Plugin::getInstance()->getSales()->getAllSales();

        return $this->asJson(array_values($sales));
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     */
    public function actionGetSalesByProductId(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();
        $id = $request->getParam('id', null);

        if (!$id) {
            return $this->asErrorJson(Craft::t('commerce', 'Product ID is required.'));
        }

        $product = Plugin::getInstance()->getProducts()->getProductById($id);

        if (!$product) {
            return $this->asErrorJson(Craft::t('commerce', 'No product available.'));
        }

        $sales = [];
        foreach ($product->getVariants(true) as $variant) {
            $variantSales = Plugin::getInstance()->getSales()->getSalesRelatedToPurchasable($variant);
            foreach ($variantSales as $sale) {
                if (!ArrayHelper::firstWhere($sales, 'id', $sale->id)) {
                    /** @var Sale $sale */
                    $saleArray = $sale->toArray();
                    $saleArray['cpEditUrl'] = $sale->getCpEditUrl();
                    $sales[] = $saleArray;
                }
            }
        }

        return $this->asJson([
            'success' => true,
            'sales' => $sales,
        ]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     */
    public function actionGetSalesByPurchasableId(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();
        $id = $request->getParam('id', null);

        if (!$id) {
            return $this->asErrorJson(Craft::t('commerce', 'Purchasable ID is required.'));
        }

        $purchasable = Plugin::getInstance()->getPurchasables()->getPurchasableById($id);

        if (!$purchasable) {
            return $this->asErrorJson(Craft::t('commerce', 'No purchasable available.'));
        }

        $sales = [];
        $purchasableSales = Plugin::getInstance()->getSales()->getSalesRelatedToPurchasable($purchasable);
        foreach ($purchasableSales as $sale) {
            if (!ArrayHelper::firstWhere($sales, 'id', $sale->id)) {
                /** @var Sale $sale */
                $saleArray = $sale->toArray();
                $saleArray['cpEditUrl'] = $sale->getCpEditUrl();
                $sales[] = $saleArray;
            }
        }

        return $this->asJson([
            'success' => true,
            'sales' => $sales,
        ]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     */
    public function actionAddPurchasableToSale(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();
        $ids = $request->getParam('ids', []);
        $saleId = $request->getParam('saleId', null);

        if (empty($ids) || !$saleId) {
            return $this->asErrorJson(Craft::t('commerce', 'Purchasable ID and Sale ID are required.'));
        }

        $purchasables = [];
        foreach ($ids as $id) {
            $purchasables[] = Plugin::getInstance()->getPurchasables()->getPurchasableById($id);
        }

        $sale = Plugin::getInstance()->getSales()->getSaleById($saleId);

        if (empty($purchasables) || count($purchasables) != count($ids) || !$sale) {
            return $this->asErrorJson(Craft::t('commerce', 'Unable to retrieve Sale and Purchasable.'));
        }

        $salePurchasableIds = $sale->getPurchasableIds();

        array_push($salePurchasableIds, ...$ids);
        $sale->setPurchasableIds(array_unique($salePurchasableIds));

        if (!Plugin::getInstance()->getSales()->saveSale($sale)) {
            return $this->asErrorJson(Craft::t('commerce', 'Couldn’t save sale.'));
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws \yii\db\Exception
     * @since 3.0
     */
    public function actionUpdateStatus(): void
    {
        $this->requirePostRequest();
        $ids = Craft::$app->getRequest()->getRequiredBodyParam('ids');
        $status = Craft::$app->getRequest()->getRequiredBodyParam('status');

        if (empty($ids)) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t updated sales status.'));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        $sales = SaleRecord::find()
            ->where(['id' => $ids])
            ->all();

        /** @var SaleRecord $sale */
        foreach ($sales as $sale) {
            $sale->enabled = ($status == 'enabled');
            $sale->save();
        }
        $transaction->commit();

        $this->setSuccessFlash(Craft::t('commerce', 'Sales updated.'));
    }


    /**
     * @param $variables
     * @throws InvalidConfigException
     */
    private function _populateVariables(&$variables): void
    {
        /** @var Sale $sale */
        $sale = $variables['sale'];

        if ($sale->id) {
            $variables['title'] = $sale->name;
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

        $localeData = Craft::$app->getLocale();
        $variables['percentSymbol'] = $localeData->getNumberSymbol(Locale::SYMBOL_PERCENT);
        $primaryCurrencyIso = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $variables['currencySymbol'] = $localeData->getCurrencySymbol($primaryCurrencyIso);

        if (isset($variables['sale']->applyAmount) && $variables['sale']->applyAmount !== null) {
            if ($sale->apply == SaleRecord::APPLY_BY_PERCENT || $sale->apply == SaleRecord::APPLY_TO_PERCENT) {
                $amount = -(float)$variables['sale']->applyAmount * 100;
                $variables['sale']->applyAmount = Craft::$app->getFormatter()->asDecimal($amount);
            } else {
                $variables['sale']->applyAmount = Craft::$app->getFormatter()->asDecimal(-(float)$variables['sale']->applyAmount);
            }
        }

        $variables['categoryElementType'] = Category::class;
        $variables['categories'] = null;
        $categories = $categoryIds = [];

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('categoryIds')) {
            $categoryIds = explode('|', Craft::$app->getRequest()->getParam('categoryIds'));
        } else {
            $categoryIds = $sale->getCategoryIds();
        }

        foreach ($categoryIds as $categoryId) {
            $id = (int)$categoryId;
            $categories[] = Craft::$app->getElements()->getElementById($id);
        }

        $variables['categories'] = $categories;

        $variables['categoryRelationshipType'] = [
            SaleRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE => Craft::t('commerce', 'Source - The category relationship field is on the purchasable'),
            SaleRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET => Craft::t('commerce', 'Target - The purchasable relationship field is on the category'),
            SaleRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH => Craft::t('commerce', 'Either (Default) - The relationship field is on the purchasable or the category'),
        ];

        $variables['purchasables'] = null;
        $purchasables = $purchasableIds = [];

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('purchasableIds')) {
            $purchasableIdsFromUrl = explode('|', Craft::$app->getRequest()->getParam('purchasableIds'));
            $purchasableIds = [];
            foreach ($purchasableIdsFromUrl as $purchasableId) {
                $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
                if ($purchasable && $purchasable instanceof Product) {
                    foreach ($purchasable->getVariants(true) as $variant) {
                        $purchasableIds[] = $variant->getId();
                    }
                } else {
                    $purchasableIds[] = $purchasableId;
                }
            }
        } else {
            $purchasableIds = $sale->getPurchasableIds();
        }

        foreach ($purchasableIds as $purchasableId) {
            $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
            if ($purchasable && $purchasable instanceof PurchasableInterface) {
                $class = get_class($purchasable);
                $purchasables[$class] = $purchasables[$class] ?? [];
                $purchasables[$class][] = $purchasable;
            }
        }
        $variables['purchasables'] = $purchasables;

        $variables['purchasableTypes'] = [];
        $purchasableTypes = Plugin::getInstance()->getPurchasables()->getAllPurchasableElementTypes();

        /** @var Purchasable $purchasableType */
        foreach ($purchasableTypes as $purchasableType) {
            $variables['purchasableTypes'][] = [
                'name' => $purchasableType::displayName(),
                'elementType' => $purchasableType,
            ];
        }
    }
}
