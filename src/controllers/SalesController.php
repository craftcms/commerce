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
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Sale as SaleRecord;
use craft\elements\Category;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\i18n\Locale;
use Exception;
use function explode;
use function get_class;
use Throwable;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

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
     * @param int|null $id
     * @param Sale|null $sale
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, Sale $sale = null): Response
    {
        $variables = compact('id', 'sale');

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

        $this->_populateVariables($variables);

        return $this->renderTemplate('commerce/promotions/sales/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws \yii\base\Exception
     * @throws BadRequestHttpException
     */
    public function actionSave()
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
            'dateTo'
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
        $sale->ignorePrevious = $request->getBodyParam('ignorePrevious');
        $sale->stopProcessing = $request->getBodyParam('stopProcessing');
        $sale->categoryRelationshipType = $request->getBodyParam('categoryRelationshipType');

        if ($sale->apply == SaleRecord::APPLY_BY_PERCENT || $sale->apply == SaleRecord::APPLY_TO_PERCENT) {
            $localeData = Craft::$app->getLocale();
            $percentSign = $localeData->getNumberSymbol(Locale::SYMBOL_PERCENT);

            if (strpos($applyAmount, $percentSign) || (float)$applyAmount >= 1) {
                $sale->applyAmount = (float)$applyAmount / -100;
            } else {
                $sale->applyAmount = (float)$applyAmount * -1;
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
            Craft::$app->getSession()->setNotice(Plugin::t('Sale saved.'));
            $this->redirectToPostedUrl($sale);
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save sale.'));
        }

        $variables = [
            'sale' => $sale
        ];
        $this->_populateVariables($variables);

        Craft::$app->getUrlManager()->setRouteParams($variables);
    }

    /**
     *
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        if ($success = Plugin::getInstance()->getSales()->reorderSales($ids)) {
            return $this->asJson(['success' => $success]);
        }

        return $this->asJson(['error' => Plugin::t('Couldn’t reorder sales.')]);
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
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getSales()->deleteSaleById($id);
        return $this->asJson(['success' => true]);
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
            return $this->asErrorJson(Plugin::t('Product ID is required.'));
        }

        $product = Plugin::getInstance()->getProducts()->getProductById($id);

        if (!$product) {
            return $this->asErrorJson(Plugin::t('No product available.'));
        }

        $sales = [];
        foreach ($product->getVariants() as $variant) {
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
            return $this->asErrorJson(Plugin::t('Purchasable ID is required.'));
        }

        $purchasable = Plugin::getInstance()->getPurchasables()->getPurchasableById($id);

        if (!$purchasable) {
            return $this->asErrorJson(Plugin::t('No purchasable available.'));
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
            return $this->asErrorJson(Plugin::t('Purchasable ID and Sale ID are required.'));
        }

        $purchasables = [];
        foreach ($ids as $id) {
            $purchasables[] = Plugin::getInstance()->getPurchasables()->getPurchasableById($id);
        }

        $sale = Plugin::getInstance()->getSales()->getSaleById($saleId);

        if (empty($purchasables) || count($purchasables) != count($ids) || !$sale) {
            return $this->asErrorJson(Plugin::t('Unable to retrieve Sale and Purchasable.'));
        }

        $salePurchasableIds = $sale->getPurchasableIds();

        array_push($salePurchasableIds, ...$ids);
        $sale->setPurchasableIds(array_unique($salePurchasableIds));

        if (!Plugin::getInstance()->getSales()->saveSale($sale)) {
            return $this->asErrorJson(Plugin::t('Couldn’t save sale.'));
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\db\Exception
     * @since 3.0
     */
    public function actionUpdateStatus()
    {
        $this->requirePostRequest();
        $ids = Craft::$app->getRequest()->getRequiredBodyParam('ids');
        $status = Craft::$app->getRequest()->getRequiredBodyParam('status');

        if (empty($ids)) {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t updated sales status.'));
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

        Craft::$app->getSession()->setNotice(Plugin::t('Sales updated.'));
    }


    /**
     * @param $variables
     * @throws InvalidConfigException
     */
    private function _populateVariables(&$variables)
    {
        /** @var Sale $sale */
        $sale = $variables['sale'];

        if ($sale->id) {
            $variables['title'] = $sale->name;
        } else {
            $variables['title'] = Plugin::t('Create a new sale');
        }

        //getting user groups map
        if (Craft::$app->getEdition() == Craft::Pro) {
            $groups = Craft::$app->getUserGroups()->getAllGroups();
            $variables['groups'] = ArrayHelper::map($groups, 'id', 'name');
        } else {
            $variables['groups'] = [];
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
            SaleRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE => Plugin::t('Source'),
            SaleRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET => Plugin::t('Target'),
            SaleRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH => Plugin::t('Both'),
        ];

        $variables['purchasables'] = null;
        $purchasables = $purchasableIds = [];

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('purchasableIds')) {
            $purchasableIdsFromUrl = explode('|', Craft::$app->getRequest()->getParam('purchasableIds'));
            $purchasableIds = [];
            foreach ($purchasableIdsFromUrl as $purchasableId) {
                $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
                if ($purchasable && $purchasable instanceof Product) {
                    foreach ($purchasable->getVariants() as $variant) {
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
                'elementType' => $purchasableType
            ];
        }
    }
}
