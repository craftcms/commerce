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
    // Public Methods
    // =========================================================================

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
        $variables = [
            'id' => $id,
            'sale' => $sale
        ];

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
     * @throws \Exception
     * @throws \yii\base\Exception
     * @throws \yii\web\BadRequestHttpException
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
            'apply',
            'enabled'
        ];
        $request = Craft::$app->getRequest();

        foreach ($fields as $field) {
            $sale->$field = $request->getBodyParam($field);
        }

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
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Sale saved.'));
            $this->redirectToPostedUrl($sale);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save sale.'));
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

        return $this->asJson(['error' => Craft::t('commerce', 'Couldn’t reorder sales.')]);
    }

    /**
     * @return Response
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getSales()->deleteSaleById($id);
        return $this->asJson(['success' => true]);
    }

    // Public Methods
    // =========================================================================

    /**
     * @param $variables
     * @throws \yii\base\InvalidConfigException
     */
    private function _populateVariables(&$variables)
    {
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

        $variables['categoryElementType'] = Category::class;
        $variables['categories'] = null;
        $categories = $categoryIds = [];

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('categoryIds')) {
            $categoryIds = \explode('|', Craft::$app->getRequest()->getParam('categoryIds'));
        } else {
            $categoryIds = $variables['sale']->getCategoryIds();
        }

        foreach ($categoryIds as $categoryId) {
            $id = (int)$categoryId;
            $categories[] = Craft::$app->getElements()->getElementById($id);
        }

        $variables['categories'] = $categories;


        $variables['purchasables'] = null;
        $purchasables = $purchasableIds = [];

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('purchasableIds')) {
            $purchasableIdsFromUrl = \explode('|', Craft::$app->getRequest()->getParam('purchasableIds'));
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
            $purchasableIds = $variables['sale']->getPurchasableIds();
        }

        foreach ($purchasableIds as $purchasableId) {
            $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
            if ($purchasable && $purchasable instanceof PurchasableInterface) {
                $class = \get_class($purchasable);
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
