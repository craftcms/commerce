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
use craft\elements\Entry;
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
use yii\web\ForbiddenHttpException;
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
class SalesController extends BaseStoreManagementController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('commerce-managePromotions');

        if (!Plugin::getInstance()->getSales()->canUseSales()) {
            throw new ForbiddenHttpException('Unable to use sales while using multi store or pricing rules.');
        }

        return true;
    }

    /**
     * @throws InvalidConfigException
     */
    public function actionIndex(?string $storeHandle = null): Response
    {
        $sales = Plugin::getInstance()->getSales()->getAllSales();
        if (empty($sales)) {
            return $this->redirect('commerce/store-management/' . $storeHandle . '/pricing-rules');
        }

        return $this->renderTemplate('commerce/promotions/sales/index', compact('sales'));
    }

    /**
     * @param int|null $id
     * @param Sale|null $sale
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionEdit(int $id = null, Sale $sale = null, ?string $storeHandle = null): Response
    {
        if ($id === null) {
            $this->requirePermission('commerce-createSales');
        } else {
            $this->requirePermission('commerce-editSales');
        }

        $variables = compact('id', 'sale');

        if ($storeHandle) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
            if ($store === null) {
                throw new InvalidConfigException('Invalid store.');
            }
        } else {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }
        $variables['storeHandle'] = $store->handle;

        $variables['isNewSale'] = false;

        if (!$variables['sale']) {
            if ($variables['id']) {
                $variables['sale'] = Plugin::getInstance()->getSales()->getSaleById($variables['id']);

                if (!$variables['sale']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['sale'] = new Sale();
                $variables['isNewSale'] = true;
                $variables['sale']->allCategories = true;
                $variables['sale']->allPurchasables = true;
                $variables['sale']->allGroups = true;
            }
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['sale'], prepend: true);

        $this->_populateVariables($variables);

        return $this->renderTemplate('commerce/promotions/sales/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws \yii\base\Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $sale = new Sale();

        // Shared attributes
        if ($sale->id === null) {
            $this->requirePermission('commerce-createSales');
        } else {
            $this->requirePermission('commerce-editSales');
        }

        $sale->id = $this->request->getBodyParam('id');
        $sale->name = $this->request->getBodyParam('name');
        $sale->description = $this->request->getBodyParam('description');
        $sale->apply = $this->request->getBodyParam('apply');
        $sale->enabled = (bool)$this->request->getBodyParam('enabled');

        $dateFields = [
            'dateFrom',
            'dateTo',
        ];
        foreach ($dateFields as $field) {
            if (($date = $this->request->getBodyParam($field)) !== false) {
                $sale->$field = DateTimeHelper::toDateTime($date) ?: null;
            } else {
                $sale->$field = $sale->$date;
            }
        }

        $applyAmount = $this->request->getBodyParam('applyAmount');
        $sale->sortOrder = (int)$this->request->getBodyParam('sortOrder');
        $sale->ignorePrevious = (bool)$this->request->getBodyParam('ignorePrevious');
        $sale->stopProcessing = (bool)$this->request->getBodyParam('stopProcessing');
        $sale->categoryRelationshipType = $this->request->getBodyParam('categoryRelationshipType', $sale->categoryRelationshipType);

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

        // Set purchasable conditions
        $allPurchasables = !$this->request->getBodyParam('allPurchasables', false);
        if ($sale->allPurchasables = $allPurchasables) {
            $sale->setPurchasableIds([]);
        } else {
            $purchasables = [];
            $purchasableGroups = $this->request->getBodyParam('purchasables') ?: [];
            foreach ($purchasableGroups as $group) {
                if (is_array($group)) {
                    array_push($purchasables, ...$group);
                }
            }
            $sale->setPurchasableIds($purchasables);
        }

        // False in the allCategories param is true in the DB
        $allCategories = !$this->request->getBodyParam('allCategories', false);
        // Set category conditions
        if ($sale->allCategories = $allCategories) {
            $sale->setCategoryIds([]);
        } else {
            $relatedElements = [];
            $relatedElementByType = $this->request->getBodyParam('relatedElements') ?: [];
            foreach ($relatedElementByType as $type) {
                if (is_array($type)) {
                    array_push($relatedElements, ...$type);
                }
            }
            $relatedElements = array_unique($relatedElements);
            $sale->setCategoryIds($relatedElements);
        }

        // Set user group conditions
        // Default value is `true` to catch projects that do not have user groups and therefore do not have this field
        if ($sale->allGroups = (bool)$this->request->getBodyParam('allGroups', true)) {
            $sale->setUserGroupIds([]);
        } else {
            $groups = $this->request->getBodyParam('groups', []);
            if (!$groups) {
                $groups = [];
            }
            $sale->setUserGroupIds($groups);
        }

        // Save it
        if (Plugin::getInstance()->getSales()->saveSale($sale)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Sale saved.'));
            return $this->redirectToPostedUrl($sale);
        }

        $this->setFailFlash(Craft::t('commerce', 'Couldn’t save sale.'));

        $variables = [
            'sale' => $sale,
        ];
        $this->_populateVariables($variables);

        Craft::$app->getUrlManager()->setRouteParams($variables);

        return null;
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode($this->request->getRequiredBodyParam('ids'));
        if (!Plugin::getInstance()->getSales()->reorderSales($ids)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t reorder sales.'));
        }

        return $this->asSuccess();
    }

    /**
     * @throws Exception
     * @throws Throwable
     * @throws StaleObjectException
     * @throws BadRequestHttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePermission('commerce-deleteSales');
        $this->requirePostRequest();

        $id = $this->request->getBodyParam('id');
        $ids = $this->request->getBodyParam('ids');

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
            return $this->asSuccess();
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Sales deleted.'));

        return $this->redirect($this->request->getReferrer());
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionGetAllSales(): Response
    {
        $this->requireAcceptsJson();
        $sales = Plugin::getInstance()->getSales()->getAllSales();

        return $this->asJson(array_values($sales));
    }

    /**
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     */
    public function actionGetSalesByProductId(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $id = $this->request->getParam('id');

        if (!$id) {
            return $this->asFailure(Craft::t('commerce', 'Product ID is required.'));
        }

        $product = Plugin::getInstance()->getProducts()->getProductById($id);

        if (!$product) {
            return $this->asFailure(Craft::t('commerce', 'No product available.'));
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

        return $this->asSuccess(data: [
            'sales' => $sales,
        ]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     */
    public function actionGetSalesByPurchasableId(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $id = $this->request->getParam('id');

        if (!$id) {
            return $this->asFailure(Craft::t('commerce', 'Purchasable ID is required.'));
        }

        $purchasable = Plugin::getInstance()->getPurchasables()->getPurchasableById($id);

        if (!$purchasable) {
            return $this->asFailure(Craft::t('commerce', 'No purchasable available.'));
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

        return $this->asSuccess(data: [
            'sales' => $sales,
        ]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     */
    public function actionAddPurchasableToSale(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $ids = $this->request->getParam('ids', []);
        $saleId = $this->request->getParam('saleId');

        if (empty($ids) || !$saleId) {
            return $this->asFailure(Craft::t('commerce', 'Purchasable ID and Sale ID are required.'));
        }

        $purchasables = [];
        foreach ($ids as $id) {
            $purchasables[] = Plugin::getInstance()->getPurchasables()->getPurchasableById($id);
        }

        $sale = Plugin::getInstance()->getSales()->getSaleById($saleId);

        if (empty($purchasables) || count($purchasables) != count($ids) || !$sale) {
            return $this->asFailure(Craft::t('commerce', 'Unable to retrieve Sale and Purchasable.'));
        }

        $salePurchasableIds = $sale->getPurchasableIds();

        array_push($salePurchasableIds, ...$ids);
        if (!empty($salePurchasableIds)) {
            $sale->allPurchasables = false;
        }
        $sale->setPurchasableIds(array_unique($salePurchasableIds));

        if (!Plugin::getInstance()->getSales()->saveSale($sale)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t save sale.'));
        }

        return $this->asSuccess();
    }

    /**
     * @throws BadRequestHttpException
     * @throws \yii\db\Exception
     * @throws ForbiddenHttpException
     * @since 3.0
     */
    public function actionUpdateStatus(): void
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-editSales');

        $ids = $this->request->getRequiredBodyParam('ids');
        $status = $this->request->getRequiredBodyParam('status');


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

        $variables['percentSymbol'] = Craft::$app->getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
        $primaryCurrencyIso = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $variables['currencySymbol'] = Craft::$app->getLocale()->getCurrencySymbol($primaryCurrencyIso);

        $variables['saleApplyAmount'] = '';
        if (isset($variables['sale']->applyAmount) && $variables['sale']->applyAmount !== null) {
            if ($sale->apply == SaleRecord::APPLY_BY_PERCENT || $sale->apply == SaleRecord::APPLY_TO_PERCENT) {
                $amount = -(float)$variables['sale']->applyAmount * 100;
                $variables['saleApplyAmount'] = Craft::$app->getFormatter()->asDecimal($amount);
            } else {
                $variables['saleApplyAmount'] = Craft::$app->getFormatter()->asDecimal(-(float)$variables['sale']->applyAmount);
            }
        }

        $variables['categoryElementType'] = Category::class;
        $variables['entryElementType'] = Entry::class;
        $variables['categories'] = null;
        $variables['entries'] = null;

        $categories = [];
        $entries = [];

        if (empty($variables['id']) && $this->request->getParam('categoryIds')) {
            $categoryIds = explode('|', $this->request->getParam('categoryIds'));
        } else {
            $categoryIds = $sale->getCategoryIds();
        }

        foreach ($categoryIds as $categoryId) {
            $id = (int)$categoryId;
            $element = Craft::$app->getElements()->getElementById($id);

            if ($element instanceof Category) {
                $categories[] = $element;
            } elseif ($element instanceof Entry) {
                $entries[] = $element;
            }
        }

        $variables['categories'] = $categories;
        $variables['entries'] = $entries;

        $variables['elementRelationshipTypeOptions'] = [
            SaleRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE => Craft::t('commerce', 'The purchasable defines the relationship'),
            SaleRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET => Craft::t('commerce', 'The purchasable is related by another element'),
            SaleRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH => Craft::t('commerce', 'Either way'),
        ];

        $variables['purchasables'] = null;
        $purchasables = [];

        if (empty($variables['id']) && $this->request->getParam('purchasableIds')) {
            $purchasableIdsFromUrl = explode('|', $this->request->getParam('purchasableIds'));
            $purchasableIds = [];
            foreach ($purchasableIdsFromUrl as $purchasableId) {
                $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
                if ($purchasable instanceof Product) {
                    foreach ($purchasable->getVariants(true) as $variant) {
                        $purchasableIds[] = $variant->getId();
                    }
                } else {
                    $purchasableIds[] = $purchasableId;
                }

                if (!empty($purchasableIds)) {
                    $variables['sale']->allPurchasables = false;
                } else {
                    $variables['sale']->allPurchasables = true;
                }
            }
        } else {
            $purchasableIds = $sale->getPurchasableIds();
        }

        foreach ($purchasableIds as $purchasableId) {
            $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
            if ($purchasable instanceof PurchasableInterface) {
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
